<?php

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'posts',
            'id' => $this->id,
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'excerpt' => $this->excerpt,
                'content' => $this->content,
                'featured_image' => $this->featured_image,
                'status' => $this->status,
                'is_featured' => $this->is_featured,
                'allow_comments' => $this->allow_comments,
                'views_count' => $this->views_count,
                'reading_time' => $this->getReadingTime(),
                'meta' => $this->meta,
                'seo' => [
                    'title' => $this->getSeoTitle(),
                    'description' => $this->getSeoDescription(),
                    'keywords' => $this->getSeoKeywords(),
                ],
                'url' => $this->getUrl(),
                'is_published' => $this->isPublished(),
                'published_at' => $this->published_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'author' => $this->when($this->relationLoaded('author'), function () {
                    return [
                        'data' => [
                            'type' => 'users',
                            'id' => $this->author->id,
                            'attributes' => [
                                'name' => $this->author->first_name . ' ' . $this->author->last_name,
                                'email' => $this->author->email,
                                'avatar' => $this->author->avatar,
                            ],
                        ],
                    ];
                }),
                'categories' => $this->when($this->relationLoaded('categories'), function () {
                    return [
                        'data' => $this->categories->map(fn ($category) => [
                            'type' => 'categories',
                            'id' => $category->id,
                            'attributes' => [
                                'name' => $category->name,
                                'slug' => $category->slug,
                            ],
                        ]),
                    ];
                }),
                'tags' => $this->when($this->relationLoaded('tags'), function () {
                    return [
                        'data' => $this->tags->map(fn ($tag) => [
                            'type' => 'tags',
                            'id' => $tag->id,
                            'attributes' => [
                                'name' => $tag->name,
                                'slug' => $tag->slug,
                            ],
                        ]),
                    ];
                }),
                'comments' => $this->when($this->relationLoaded('comments'), function () {
                    return [
                        'data' => $this->comments->map(fn ($comment) => [
                            'type' => 'comments',
                            'id' => $comment->id,
                            'attributes' => [
                                'author_name' => $comment->author_name,
                                'content' => $comment->content,
                                'is_approved' => $comment->is_approved,
                                'created_at' => $comment->created_at->toIso8601String(),
                            ],
                        ]),
                        'meta' => [
                            'count' => $this->comments->count(),
                        ],
                    ];
                }),
            ],
        ];
    }
}
