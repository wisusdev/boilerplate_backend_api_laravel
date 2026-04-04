<?php

namespace Modules\Pages\Services;

use Modules\Pages\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class PageService
{
    /**
     * Get all pages with optional filters.
     */
    public function getAll(array $filters = [])
    {
        $query = Page::with(['author', 'parent']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('content', 'like', "%{$filters['search']}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get a page by ID or slug.
     */
    public function find(string $identifier): ?Page
    {
        if (Str::isUuid($identifier)) {
            return Page::with(['author', 'parent', 'children'])->find($identifier);
        }

        return Page::with(['author', 'parent', 'children'])
            ->where('slug', $identifier)
            ->first();
    }

    /**
     * Get a published page by slug.
     */
    public function findPublished(string $slug): ?Page
    {
        return Page::published()
            ->with(['author', 'parent', 'children'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Create a new page.
     */
    public function create(array $data): Page
    {
        $data['author_id'] = $data['author_id'] ?? Auth::id();

        if (isset($data['content_json'])) {
            $data['content_html'] = $this->renderGrapesJsContent($data['content_json']);
        }

        return Page::create($data);
    }

    /**
     * Update a page.
     */
    public function update(Page $page, array $data): Page
    {
        if (isset($data['content_json'])) {
            $data['content_html'] = $this->renderGrapesJsContent($data['content_json']);
        }

        $page->update($data);

        return $page->fresh();
    }

    /**
     * Delete a page.
     */
    public function delete(Page $page): bool
    {
        return $page->delete();
    }

    /**
     * Restore a soft-deleted page.
     */
    public function restore(string $id): ?Page
    {
        $page = Page::withTrashed()->find($id);

        if ($page && $page->trashed()) {
            $page->restore();
            return $page;
        }

        return null;
    }

    /**
     * Force delete a page.
     */
    public function forceDelete(string $id): bool
    {
        $page = Page::withTrashed()->find($id);

        if ($page) {
            return $page->forceDelete();
        }

        return false;
    }

    /**
     * Duplicate a page.
     */
    public function duplicate(Page $page): Page
    {
        $newPage = $page->replicate(['slug', 'published_at']);
        $newPage->title = $page->title . ' (Copy)';
        $newPage->slug = Str::slug($newPage->title);
        $newPage->status = 'draft';
        $newPage->author_id = Auth::id();
        $newPage->save();

        return $newPage;
    }

    /**
     * Render GrapesJS JSON content to HTML.
     */
    protected function renderGrapesJsContent(array $contentJson): string
    {
        // GrapesJS stores the HTML directly in the components
        // This is a simplified renderer - GrapesJS SDK handles this on the frontend
        $html = $contentJson['html'] ?? '';

        return $html;
    }

    /**
     * Get page tree (hierarchical structure).
     */
    public function getTree(): array
    {
        $pages = Page::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return $this->buildTree($pages);
    }

    /**
     * Build tree from pages collection.
     */
    protected function buildTree($pages): array
    {
        return $pages->map(function ($page) {
            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'url' => $page->getUrl(),
                'children' => $page->children->isNotEmpty()
                    ? $this->buildTree($page->children)
                    : [],
            ];
        })->toArray();
    }

    /**
     * Reorder pages.
     */
    public function reorder(array $items): void
    {
        foreach ($items as $index => $item) {
            Page::where('id', $item['id'])->update([
                'order' => $index,
                'parent_id' => $item['parent_id'] ?? null,
            ]);
        }
    }

    /**
     * Get available templates for pages.
     */
    public function getTemplates(): array
    {
        $theme = app('theme')->active();

        if ($theme) {
            return $theme->getTemplates()['page'] ?? ['default' => 'Default Page'];
        }

        return ['default' => 'Default Page'];
    }
}
