<?php

namespace Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Media\Models\Media;
use Modules\Media\Models\MediaFolder;

class MediaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = Media::query();

        $currentFolder = null;
        if ($request->folder_id) {
            $currentFolder = MediaFolder::findOrFail($request->folder_id);
            $query->where('folder', $currentFolder->id);
        }

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->type) {
            match ($request->type) {
                'image'    => $query->images(),
                'video'    => $query->videos(),
                'audio'    => $query->audio(),
                'document' => $query->documents(),
                default    => null,
            };
        }

        $media = $query->orderByDesc('created_at')->paginate(24)->withQueryString();

        // Return JSON if requested
        if ($request->wantsJson()) {
            return response()->json([
                'media' => $media->map(fn ($m) => [
                    'id'            => $m->id,
                    'url'           => Storage::url($m->path),
                    'filename'      => $m->name,
                    'original_name' => $m->file_name,
                    'type'          => $m->mime_type,
                    'size'          => $m->size,
                ]),
                'pagination' => [
                    'current_page' => $media->currentPage(),
                    'total'        => $media->total(),
                    'per_page'     => $media->perPage(),
                ],
            ]);
        }

        $folders = MediaFolder::whereNull('parent_id')
            ->with('children')
            ->get()
            ->map(fn ($f) => $f->toArray())
            ->toArray();

        return view('media::admin.index', compact('media', 'folders', 'currentFolder'));
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();
        $name     = pathinfo($filename, PATHINFO_FILENAME);
        $folder   = 'media/' . now()->format('Y/m');
        $path     = $file->store($folder, 'public');

        $width = $height = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        }

        $media = Media::create([
            'name'        => $name,
            'filename'    => $filename,
            'path'        => $path,
            'disk'        => 'public',
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
            'width'       => $width,
            'height'      => $height,
            'folder'      => $request->folder_id ?? '/',
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'id'   => $media->id,
            'name' => $media->name,
            'url'  => $media->getUrl(),
            'type' => $media->type,
            'size' => $media->getFormattedSize(),
        ]);
    }

    public function update(Request $request, Media $medium): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['nullable', 'string', 'max:255'],
            'alt'         => ['nullable', 'string', 'max:255'],
            'caption'     => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
        ]);

        $medium->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'media' => $medium->fresh()]);
    }

    public function destroy(Media $medium): JsonResponse
    {
        $medium->forceDelete();

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
        ]);

        Media::whereIn('id', $request->ids)->get()->each->forceDelete();

        return response()->json(['success' => true]);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid', 'exists:media_folders,id'],
        ]);

        $folder = MediaFolder::create($validated);

        return response()->json([
            'id'   => $folder->id,
            'name' => $folder->name,
            'slug' => $folder->slug,
        ]);
    }

    public function deleteFolder(MediaFolder $folder): JsonResponse
    {
        Media::where('folder', $folder->id)->update(['folder' => '/']);
        $folder->delete();

        return response()->json(['success' => true]);
    }

    public function picker(Request $request): View|JsonResponse
    {
        $query = Media::query();

        if ($request->type === 'image') {
            $query->images();
        }

        $media = $query->orderByDesc('created_at')->paginate(24)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($media);
        }

        return view('media::admin.picker', compact('media'));
    }
}
