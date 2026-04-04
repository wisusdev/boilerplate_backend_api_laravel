<?php

namespace Modules\Media\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Media\Models\Media;
use Modules\Media\Models\MediaFolder;
use Modules\Media\Services\MediaService;

class MediaController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    /**
     * Display a listing of media.
     */
    public function index(Request $request): JsonResponse
    {
        $media = $this->mediaService->getAll($request->all());

        return response()->json($media);
    }

    /**
     * Upload media files.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:' . (config('media.max_file_size', 10240)),
            'folder_id' => 'nullable|uuid|exists:media_folders,id',
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
        ]);

        $result = $this->mediaService->uploadMultiple(
            $request->file('files'),
            $request->folder_id
        );

        if (count($result['errors']) > 0) {
            return response()->json([
                'message' => 'Some files failed to upload.',
                'uploaded' => $result['uploaded'],
                'errors' => $result['errors'],
            ], 207);
        }

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'data' => $result['uploaded'],
        ], 201);
    }

    /**
     * Display the specified media.
     */
    public function show(Media $medium): JsonResponse
    {
        return response()->json(['data' => $medium]);
    }

    /**
     * Update the specified media.
     */
    public function update(Request $request, Media $medium): JsonResponse
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'folder_id' => 'nullable|uuid|exists:media_folders,id',
            'meta' => 'nullable|array',
        ]);

        $medium = $this->mediaService->update($medium, $validated);

        return response()->json(['data' => $medium]);
    }

    /**
     * Remove the specified media.
     */
    public function destroy(Media $medium): JsonResponse
    {
        $this->mediaService->delete($medium);

        return response()->json(null, 204);
    }

    /**
     * Bulk delete media.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:media,id',
        ]);

        $deleted = 0;
        foreach ($request->ids as $id) {
            $media = Media::find($id);
            if ($media) {
                $this->mediaService->delete($media);
                $deleted++;
            }
        }

        return response()->json([
            'message' => "$deleted files deleted successfully.",
        ]);
    }

    /**
     * Move media to folder.
     */
    public function move(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:media,id',
            'folder_id' => 'nullable|uuid|exists:media_folders,id',
        ]);

        foreach ($request->ids as $id) {
            $media = Media::find($id);
            if ($media) {
                $this->mediaService->move($media, $request->folder_id);
            }
        }

        return response()->json([
            'message' => 'Files moved successfully.',
        ]);
    }

    /**
     * Get folder tree.
     */
    public function folders(): JsonResponse
    {
        $tree = $this->mediaService->getFolderTree();

        return response()->json(['data' => $tree]);
    }

    /**
     * Create a folder.
     */
    public function createFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|uuid|exists:media_folders,id',
        ]);

        $folder = $this->mediaService->createFolder(
            $validated['name'],
            $validated['parent_id'] ?? null
        );

        return response()->json([
            'message' => 'Folder created successfully.',
            'data' => $folder,
        ], 201);
    }

    /**
     * Delete a folder.
     */
    public function deleteFolder(MediaFolder $folder, Request $request): JsonResponse
    {
        try {
            $this->mediaService->deleteFolder(
                $folder,
                $request->boolean('recursive', false)
            );

            return response()->json(null, 204);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
