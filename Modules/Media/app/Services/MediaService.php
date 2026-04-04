<?php

namespace Modules\Media\Services;

use Modules\Media\Models\Media;
use Modules\Media\Models\MediaFolder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class MediaService
{
    protected string $disk;
    protected array $allowedMimeTypes;
    protected int $maxFileSize;
    protected array $imageSizes;

    public function __construct()
    {
        $this->disk = config('media.disk', 'public');
        $this->allowedMimeTypes = config('media.allowed_mime_types', [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
        $this->maxFileSize = config('media.max_file_size', 10 * 1024 * 1024); // 10MB
        $this->imageSizes = config('media.image_sizes', [
            'thumbnail' => [150, 150],
            'medium' => [300, 300],
            'large' => [1024, 1024],
        ]);
    }

    /**
     * Get all media with optional filters.
     */
    public function getAll(array $filters = [])
    {
        $query = Media::query();

        if (isset($filters['folder_id'])) {
            if ($filters['folder_id'] === 'null') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $filters['folder_id']);
            }
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('original_name', 'like', "%{$filters['search']}%")
                    ->orWhere('alt', 'like', "%{$filters['search']}%")
                    ->orWhere('title', 'like', "%{$filters['search']}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 24;

        return $query->paginate($perPage);
    }

    /**
     * Upload a file.
     */
    public function upload(UploadedFile $file, ?string $folderId = null, array $meta = []): Media
    {
        // Validate file
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds the limit.');
        }

        // Generate unique filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $name = Str::slug($originalName) . '-' . Str::random(8);
        $filename = $name . '.' . $extension;

        // Determine folder path
        $folder = $folderId ? MediaFolder::find($folderId) : null;
        $path = $folder ? $folder->getFullPath() . '/' : '';
        $path .= date('Y/m');

        // Store file
        $storedPath = $file->storeAs($path, $filename, $this->disk);

        // Get file info
        $type = $this->determineType($file->getMimeType());
        $dimensions = null;

        // Process images
        if ($type === 'image' && $this->isProcessableImage($file->getMimeType())) {
            $dimensions = $this->getImageDimensions($file);
            $this->createThumbnails($file, $path, $name, $extension);
        }

        // Create media record
        $media = Media::create([
            'name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'path' => $storedPath,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'type' => $type,
            'size' => $file->getSize(),
            'dimensions' => $dimensions,
            'folder_id' => $folderId,
            'uploaded_by' => auth()->id(),
            'alt' => $meta['alt'] ?? $originalName,
            'title' => $meta['title'] ?? $originalName,
            'caption' => $meta['caption'] ?? null,
            'meta' => $meta['meta'] ?? [],
        ]);

        return $media;
    }

    /**
     * Upload multiple files.
     */
    public function uploadMultiple(array $files, ?string $folderId = null): array
    {
        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $uploaded[] = $this->upload($file, $folderId);
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
        ];
    }

    /**
     * Delete a media file.
     */
    public function delete(Media $media): bool
    {
        // Delete physical file
        Storage::disk($media->disk)->delete($media->path);

        // Delete thumbnails
        foreach ($this->imageSizes as $sizeName => $dimensions) {
            $thumbnailPath = $this->getThumbnailPath($media->path, $sizeName);
            if (Storage::disk($media->disk)->exists($thumbnailPath)) {
                Storage::disk($media->disk)->delete($thumbnailPath);
            }
        }

        return $media->delete();
    }

    /**
     * Update media metadata.
     */
    public function update(Media $media, array $data): Media
    {
        $media->update([
            'alt' => $data['alt'] ?? $media->alt,
            'title' => $data['title'] ?? $media->title,
            'caption' => $data['caption'] ?? $media->caption,
            'meta' => $data['meta'] ?? $media->meta,
            'folder_id' => $data['folder_id'] ?? $media->folder_id,
        ]);

        return $media->fresh();
    }

    /**
     * Move media to a folder.
     */
    public function move(Media $media, ?string $folderId): Media
    {
        $media->update(['folder_id' => $folderId]);

        return $media;
    }

    /**
     * Create a folder.
     */
    public function createFolder(string $name, ?string $parentId = null): MediaFolder
    {
        return MediaFolder::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Delete a folder.
     */
    public function deleteFolder(MediaFolder $folder, bool $recursive = false): bool
    {
        if ($folder->children()->exists() || $folder->media()->exists()) {
            if (!$recursive) {
                throw new \InvalidArgumentException('Folder is not empty. Use recursive delete.');
            }

            // Delete all media in folder
            foreach ($folder->media as $media) {
                $this->delete($media);
            }

            // Delete child folders
            foreach ($folder->children as $child) {
                $this->deleteFolder($child, true);
            }
        }

        return $folder->delete();
    }

    /**
     * Get folder tree.
     */
    public function getFolderTree(): array
    {
        $folders = MediaFolder::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return $this->buildFolderTree($folders);
    }

    /**
     * Determine file type from mime type.
     */
    protected function determineType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if ($mimeType === 'application/pdf') {
            return 'document';
        }

        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) {
            return 'document';
        }

        if (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet')) {
            return 'spreadsheet';
        }

        return 'file';
    }

    /**
     * Check if image can be processed.
     */
    protected function isProcessableImage(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Get image dimensions.
     */
    protected function getImageDimensions(UploadedFile $file): array
    {
        try {
            $image = Image::read($file->getPathname());

            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create image thumbnails.
     */
    protected function createThumbnails(UploadedFile $file, string $path, string $name, string $extension): void
    {
        try {
            $image = Image::read($file->getPathname());

            foreach ($this->imageSizes as $sizeName => $dimensions) {
                $thumb = $image->scale($dimensions[0], $dimensions[1]);
                $thumbnailPath = $path . '/' . $name . '-' . $sizeName . '.' . $extension;

                Storage::disk($this->disk)->put(
                    $thumbnailPath,
                    $thumb->toJpeg(quality: 85)
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            report($e);
        }
    }

    /**
     * Get thumbnail path.
     */
    protected function getThumbnailPath(string $originalPath, string $size): string
    {
        $pathInfo = pathinfo($originalPath);

        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-' . $size . '.' . $pathInfo['extension'];
    }

    /**
     * Build folder tree recursively.
     */
    protected function buildFolderTree($folders): array
    {
        return $folders->map(function ($folder) {
            return [
                'id' => $folder->id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'children' => $folder->children->isNotEmpty()
                    ? $this->buildFolderTree($folder->children)
                    : [],
            ];
        })->toArray();
    }
}
