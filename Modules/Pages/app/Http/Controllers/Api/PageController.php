<?php

namespace Modules\Pages\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pages\Http\Requests\StorePageRequest;
use Modules\Pages\Http\Requests\UpdatePageRequest;
use Modules\Pages\Http\Resources\PageResource;
use Modules\Pages\Http\Resources\PageCollection;
use Modules\Pages\Models\Page;
use Modules\Pages\Services\PageService;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pageService
    ) {}

    /**
     * Display a listing of pages.
     */
    public function index(Request $request): PageCollection
    {
        $pages = $this->pageService->getAll($request->all());

        return new PageCollection($pages);
    }

    /**
     * Store a newly created page.
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        $page = $this->pageService->create($request->validated());

        return (new PageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified page.
     */
    public function show(string $id): PageResource
    {
        $page = $this->pageService->find($id);

        if (!$page) {
            abort(404, 'Page not found');
        }

        return new PageResource($page);
    }

    /**
     * Update the specified page.
     */
    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        $page = $this->pageService->update($page, $request->validated());

        return new PageResource($page);
    }

    /**
     * Remove the specified page.
     */
    public function destroy(Page $page): JsonResponse
    {
        $this->pageService->delete($page);

        return response()->json(null, 204);
    }

    /**
     * Restore a soft-deleted page.
     */
    public function restore(string $id): JsonResponse
    {
        $page = $this->pageService->restore($id);

        if (!$page) {
            abort(404, 'Page not found or not deleted');
        }

        return (new PageResource($page))->response();
    }

    /**
     * Force delete a page.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $deleted = $this->pageService->forceDelete($id);

        if (!$deleted) {
            abort(404, 'Page not found');
        }

        return response()->json(null, 204);
    }

    /**
     * Duplicate a page.
     */
    public function duplicate(Page $page): JsonResponse
    {
        $newPage = $this->pageService->duplicate($page);

        return (new PageResource($newPage))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get page tree structure.
     */
    public function tree(): JsonResponse
    {
        $tree = $this->pageService->getTree();

        return response()->json(['data' => $tree]);
    }

    /**
     * Reorder pages.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid|exists:pages,id',
            'items.*.parent_id' => 'nullable|uuid|exists:pages,id',
        ]);

        $this->pageService->reorder($request->items);

        return response()->json(['message' => 'Pages reordered successfully']);
    }

    /**
     * Get page revisions.
     */
    public function revisions(Page $page): JsonResponse
    {
        $revisions = $page->revisions()
            ->with('user')
            ->limit(20)
            ->get();

        return response()->json(['data' => $revisions]);
    }

    /**
     * Restore a page revision.
     */
    public function restoreRevision(Page $page, int $revisionId): PageResource
    {
        $revision = $page->revisions()->findOrFail($revisionId);
        $page->restoreRevision($revision);

        return new PageResource($page->fresh());
    }

    /**
     * Get available templates.
     */
    public function templates(): JsonResponse
    {
        $templates = $this->pageService->getTemplates();

        return response()->json(['data' => $templates]);
    }
}
