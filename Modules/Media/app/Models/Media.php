<?php

namespace Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'caption',
        'description',
        'meta',
        'uploaded_by',
        'folder',
    ];

    protected $casts = [
        'meta' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * Get the user who uploaded this media.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full URL of the media.
     */
    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get the full path of the media.
     */
    public function getFullPath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if the media is audio.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Check if the media is a document.
     */
    public function isDocument(): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        return in_array($this->mime_type, $documentMimes);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get dimensions string (for images/videos).
     */
    public function getDimensions(): ?string
    {
        if ($this->width && $this->height) {
            return $this->width . 'x' . $this->height;
        }

        return null;
    }

    /**
     * Scope for images.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope for videos.
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    /**
     * Scope for audio.
     */
    public function scopeAudio($query)
    {
        return $query->where('mime_type', 'like', 'audio/%');
    }

    /**
     * Scope for documents.
     */
    public function scopeDocuments($query)
    {
        return $query->where(function ($q) {
            $q->where('mime_type', 'like', 'application/pdf')
                ->orWhere('mime_type', 'like', 'application/msword%')
                ->orWhere('mime_type', 'like', 'application/vnd.openxmlformats%')
                ->orWhere('mime_type', 'like', 'application/vnd.ms-%')
                ->orWhere('mime_type', 'like', 'text/%');
        });
    }

    /**
     * Scope for folder.
     */
    public function scopeInFolder($query, string $folder)
    {
        return $query->where('folder', $folder);
    }

    /**
     * Get the media type derived from mime_type.
     */
    public function getTypeAttribute(): string
    {
        if ($this->isImage()) return 'image';
        if ($this->isVideo()) return 'video';
        if ($this->isAudio()) return 'audio';
        if ($this->isDocument()) return 'document';
        return 'other';
    }

    /**
     * Alias for name (used in views).
     */
    public function getOriginalNameAttribute(): string
    {
        return $this->filename ?: $this->name;
    }

    /**
     * Human readable file size (alias for getFormattedSize).
     */
    public function getHumanFileSize(): string
    {
        return $this->getFormattedSize();
    }

    /**
     * Delete the media file from storage.
     */
    public function deleteFile(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * Override delete to also remove the file.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Media $media) {
            if ($media->isForceDeleting()) {
                $media->deleteFile();
            }
        });
    }
}
