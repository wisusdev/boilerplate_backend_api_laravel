<?php

namespace Modules\Pages\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Pages\Models\Page;
use Modules\Pages\Services\PageService;
use Modules\Pages\Http\Requests\StorePageRequest;
use Modules\Pages\Http\Requests\UpdatePageRequest;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pageService
    ) {}

    /**
     * Display a listing of pages.
     */
    public function index(Request $request): View
    {
        $pages = $this->pageService->getAll($request->all());
        $tree = $this->pageService->getTree();

        return view('pages::admin.index', compact('pages', 'tree'));
    }

    /**
     * Show the form for creating a new page.
     */
    public function create(): View
    {
        $templates = $this->pageService->getTemplates();
        $parents = Page::whereNull('parent_id')->orderBy('title')->get();

        return view('pages::admin.create', compact('templates', 'parents'));
    }

    /**
     * Store a newly created page.
     */
    public function store(StorePageRequest $request): RedirectResponse
    {
        $page = $this->pageService->create($request->validated());

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', __('Page created successfully.'));
    }

    /**
     * Show the form for editing the specified page.
     */
    public function edit(Page $page): View
    {
        $page->load(['author', 'parent', 'revisions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $templates = $this->pageService->getTemplates();
        $parents = Page::where('id', '!=', $page->id)
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get();

        return view('pages::admin.edit', compact('page', 'templates', 'parents'));
    }

    /**
     * Update the specified page.
     */
    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->pageService->update($page, $request->validated());

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', __('Page updated successfully.'));
    }

    /**
     * Remove the specified page.
     */
    public function destroy(Page $page): RedirectResponse
    {
        $this->pageService->delete($page);

        return redirect()
            ->route('admin.pages.index')
            ->with('success', __('Page moved to trash.'));
    }

    /**
     * Display the GrapesJS page builder.
     */
    public function builder(Page $page): View
    {
        $page->load(['author']);
        
        return view('pages::admin.builder', compact('page'));
    }

    /**
     * Save GrapesJS content via AJAX.
     */
    public function saveBuilder(Request $request, Page $page): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'content_html' => 'required|string',
            'content_json' => 'required|array',
            'content_css' => 'nullable|array',
        ]);

        $this->pageService->update($page, [
            'content_html' => $request->content_html,
            'content_json' => $request->content_json,
            'content_css' => $request->content_css ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Page saved successfully.'),
        ]);
    }

    /**
     * Show trashed pages.
     */
    public function trash(Request $request): View
    {
        $pages = Page::onlyTrashed()
            ->with(['author'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return view('pages::admin.trash', compact('pages'));
    }

    /**
     * Restore a trashed page.
     */
    public function restore(string $id): RedirectResponse
    {
        $page = $this->pageService->restore($id);

        if (!$page) {
            return redirect()
                ->route('admin.pages.trash')
                ->with('error', __('Page not found.'));
        }

        return redirect()
            ->route('admin.pages.trash')
            ->with('success', __('Page restored successfully.'));
    }

    /**
     * Permanently delete a page.
     */
    public function forceDelete(string $id): RedirectResponse
    {
        $this->pageService->forceDelete($id);

        return redirect()
            ->route('admin.pages.trash')
            ->with('success', __('Page permanently deleted.'));
    }

    /**
     * Display a page on the frontend.
     */
    public function show(string $slug): View
    {
        $page = $this->pageService->findPublished($slug);

        if (!$page) {
            abort(404);
        }

        // Determine the template to use
        $template = $page->template ?? 'default';
        $view = "pages::templates.{$template}";

        // Fallback to default if template doesn't exist
        if (!view()->exists($view)) {
            $view = 'pages::templates.default';
        }

        return view($view, compact('page'));
    }

    /**
     * Preview a page (even if not published).
     */
    public function preview(Page $page): View
    {
        $template = $page->template ?? 'default';
        $view = "pages::templates.{$template}";

        if (!view()->exists($view)) {
            $view = 'pages::templates.default';
        }

        return view($view, compact('page'));
    }

    /**
     * Duplicate a page.
     */
    public function duplicate(Page $page): RedirectResponse
    {
        $newPage = $this->pageService->duplicate($page);

        return redirect()
            ->route('admin.pages.edit', $newPage)
            ->with('success', __('Page duplicated successfully.'));
    }

    /**
     * Show page revisions.
     */
    public function revisions(Page $page): View
    {
        $revisions = $page->revisions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('pages::admin.revisions', compact('page', 'revisions'));
    }

    /**
     * Restore a specific revision.
     */
    public function restoreRevision(Page $page, int $revisionId): RedirectResponse
    {
        $revision = $page->revisions()->findOrFail($revisionId);
        $page->restoreRevision($revision);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', __('Revision restored successfully.'));
    }
}
