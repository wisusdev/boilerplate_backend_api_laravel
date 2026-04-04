<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;
use Modules\Blog\Services\PostService;
use Modules\Blog\Http\Requests\StorePostRequest;
use Modules\Blog\Http\Requests\UpdatePostRequest;

class PostController extends Controller
{
    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * Display a listing of posts.
     */
    public function index(Request $request): View
    {
        $posts = $this->postService->getAll($request->all());
        $categories = Category::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('blog::admin.posts.index', compact('posts', 'categories', 'tags'));
    }

    /**
     * Show the form for creating a new post.
     */
    public function create(): View
    {
        $categories = Category::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('blog::admin.posts.create', compact('categories', 'tags'));
    }

    /**
     * Store a newly created post.
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = $this->postService->create($request->validated());

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', __('Post created successfully.'));
    }

    /**
     * Show the form for editing the specified post.
     */
    public function edit(Post $post): View
    {
        $post->load(['author', 'categories', 'tags']);
        $categories = Category::orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('blog::admin.posts.edit', compact('post', 'categories', 'tags'));
    }

    /**
     * Update the specified post.
     */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->postService->update($post, $request->validated());

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', __('Post updated successfully.'));
    }

    /**
     * Remove the specified post.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $this->postService->delete($post);

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', __('Post moved to trash.'));
    }

    /**
     * Show trashed posts.
     */
    public function trash(Request $request): View
    {
        $posts = Post::onlyTrashed()
            ->with(['author'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return view('blog::admin.posts.trash', compact('posts'));
    }

    /**
     * Restore a trashed post.
     */
    public function restore(string $id): RedirectResponse
    {
        $post = $this->postService->restore($id);

        if (!$post) {
            return redirect()
                ->route('admin.blog.posts.trash')
                ->with('error', __('Post not found.'));
        }

        return redirect()
            ->route('admin.blog.posts.trash')
            ->with('success', __('Post restored successfully.'));
    }

    /**
     * Permanently delete a post.
     */
    public function forceDelete(string $id): RedirectResponse
    {
        $this->postService->forceDelete($id);

        return redirect()
            ->route('admin.blog.posts.trash')
            ->with('success', __('Post permanently deleted.'));
    }

    /**
     * Display a post on the frontend.
     */
    public function show(string $slug): View
    {
        $post = $this->postService->findPublished($slug);

        if (!$post) {
            abort(404);
        }

        // Increment view count
        $this->postService->incrementViews($post);

        // Get related posts
        $relatedPosts = $this->postService->getRelated($post);

        return view('blog::posts.show', compact('post', 'relatedPosts'));
    }

    /**
     * Preview a post (even if not published).
     */
    public function preview(Post $post): View
    {
        $relatedPosts = $this->postService->getRelated($post);

        return view('blog::posts.show', compact('post', 'relatedPosts'));
    }

    /**
     * Duplicate a post.
     */
    public function duplicate(Post $post): RedirectResponse
    {
        $newPost = $this->postService->duplicate($post);

        return redirect()
            ->route('admin.blog.posts.edit', $newPost)
            ->with('success', __('Post duplicated successfully.'));
    }

    /**
     * Blog archive/listing page.
     */
    public function archive(Request $request): View
    {
        $filters = $request->only(['category', 'tag', 'search']);
        $filters['status'] = 'published';

        $posts = $this->postService->getAll($filters);
        $categories = Category::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();
        $tags = Tag::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();
        $popularPosts = $this->postService->getPopular(5);
        $featuredPosts = $this->postService->getFeatured(3);

        return view('blog::posts.archive', compact('posts', 'categories', 'tags', 'popularPosts', 'featuredPosts'));
    }

    /**
     * Posts by category.
     */
    public function byCategory(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $posts = Post::published()
            ->whereHas('categories', function ($q) use ($category) {
                $q->where('categories.id', $category->id);
            })
            ->orderBy('published_at', 'desc')
            ->paginate(15);

        $categories = Category::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();

        return view('blog::posts.category', compact('posts', 'category', 'categories'));
    }

    /**
     * Posts by tag.
     */
    public function byTag(string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $posts = Post::published()
            ->whereHas('tags', function ($q) use ($tag) {
                $q->where('tags.id', $tag->id);
            })
            ->orderBy('published_at', 'desc')
            ->paginate(15);

        $tags = Tag::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();

        return view('blog::posts.tag', compact('posts', 'tag', 'tags'));
    }
}
