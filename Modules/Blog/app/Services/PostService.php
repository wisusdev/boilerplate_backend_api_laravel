<?php

namespace Modules\Blog\Services;

use Modules\Blog\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class PostService
{
    /**
     * Get all posts with optional filters.
     */
    public function getAll(array $filters = [])
    {
        $query = Post::with(['author', 'categories', 'tags']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (isset($filters['category'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->where('slug', $filters['category']);
            });
        }

        if (isset($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('slug', $filters['tag']);
            });
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('content', 'like', "%{$filters['search']}%")
                    ->orWhere('excerpt', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get a post by ID or slug.
     */
    public function find(string $identifier): ?Post
    {
        if (Str::isUuid($identifier)) {
            return Post::with(['author', 'categories', 'tags', 'comments' => function ($q) {
                $q->approved()->whereNull('parent_id')->with('replies');
            }])->find($identifier);
        }

        return Post::with(['author', 'categories', 'tags', 'comments' => function ($q) {
            $q->approved()->whereNull('parent_id')->with('replies');
        }])->where('slug', $identifier)->first();
    }

    /**
     * Get a published post by slug.
     */
    public function findPublished(string $slug): ?Post
    {
        return Post::published()
            ->with(['author', 'categories', 'tags', 'comments' => function ($q) {
                $q->approved()->whereNull('parent_id')->with('replies');
            }])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Create a new post.
     */
    public function create(array $data): Post
    {
        $data['author_id'] = $data['author_id'] ?? Auth::id();

        $post = Post::create($data);

        // Sync categories
        if (isset($data['categories'])) {
            $post->categories()->sync($data['categories']);
        }

        // Sync tags
        if (isset($data['tags'])) {
            $this->syncTags($post, $data['tags']);
        }

        return $post->load(['categories', 'tags']);
    }

    /**
     * Update a post.
     */
    public function update(Post $post, array $data): Post
    {
        $post->update($data);

        // Sync categories
        if (isset($data['categories'])) {
            $post->categories()->sync($data['categories']);
        }

        // Sync tags
        if (isset($data['tags'])) {
            $this->syncTags($post, $data['tags']);
        }

        return $post->fresh(['categories', 'tags']);
    }

    /**
     * Delete a post.
     */
    public function delete(Post $post): bool
    {
        return $post->delete();
    }

    /**
     * Restore a soft-deleted post.
     */
    public function restore(string $id): ?Post
    {
        $post = Post::withTrashed()->find($id);

        if ($post && $post->trashed()) {
            $post->restore();
            return $post;
        }

        return null;
    }

    /**
     * Force delete a post.
     */
    public function forceDelete(string $id): bool
    {
        $post = Post::withTrashed()->find($id);

        if ($post) {
            return $post->forceDelete();
        }

        return false;
    }

    /**
     * Duplicate a post.
     */
    public function duplicate(Post $post): Post
    {
        $newPost = $post->replicate(['slug', 'published_at', 'views_count']);
        $newPost->title = $post->title . ' (Copy)';
        $newPost->slug = Str::slug($newPost->title);
        $newPost->status = 'draft';
        $newPost->author_id = Auth::id();
        $newPost->save();

        // Copy categories and tags
        $newPost->categories()->sync($post->categories->pluck('id'));
        $newPost->tags()->sync($post->tags->pluck('id'));

        return $newPost;
    }

    /**
     * Sync tags (creates new tags if they don't exist).
     */
    protected function syncTags(Post $post, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tag) {
            if (is_numeric($tag) || Str::isUuid($tag)) {
                $tagIds[] = $tag;
            } else {
                // Create tag if it doesn't exist
                $tagModel = \Modules\Blog\Models\Tag::firstOrCreate(
                    ['slug' => Str::slug($tag)],
                    ['name' => $tag]
                );
                $tagIds[] = $tagModel->id;
            }
        }

        $post->tags()->sync($tagIds);
    }

    /**
     * Get related posts.
     */
    public function getRelated(Post $post, int $limit = 4): \Illuminate\Database\Eloquent\Collection
    {
        return Post::published()
            ->where('id', '!=', $post->id)
            ->where(function ($query) use ($post) {
                $categoryIds = $post->categories->pluck('id');
                $tagIds = $post->tags->pluck('id');

                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                })->orWhereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                });
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Increment view count.
     */
    public function incrementViews(Post $post): void
    {
        $post->increment('views_count');
    }

    /**
     * Get popular posts.
     */
    public function getPopular(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Post::published()
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get featured posts.
     */
    public function getFeatured(int $limit = 3): \Illuminate\Database\Eloquent\Collection
    {
        return Post::published()
            ->where('is_featured', true)
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
