<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Models\Category;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $categories = Category::withCount('posts')
            ->orderBy('name')
            ->paginate(15);

        return view('blog::admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $parents = Category::whereNull('parent_id')->orderBy('name')->get();

        return view('blog::admin.categories.create', compact('parents'));
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|uuid|exists:categories,id',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        Category::create($validated);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', __('Category created successfully.'));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        $parents = Category::where('id', '!=', $category->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('blog::admin.categories.edit', compact('category', 'parents'));
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|uuid|exists:categories,id',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', __('Category updated successfully.'));
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->posts()->count() > 0) {
            return redirect()
                ->route('admin.blog.categories.index')
                ->with('error', __('Cannot delete category with posts. Remove posts first.'));
        }

        $category->delete();

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', __('Category deleted successfully.'));
    }
}
