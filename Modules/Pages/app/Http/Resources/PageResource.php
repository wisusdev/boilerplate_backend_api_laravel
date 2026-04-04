<?php

namespace Modules\Pages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'pages',
            'id' => $this->id,
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'content' => $this->content,
                'content_html' => $this->content_html,
                'content_json' => $this->content_json,
                'content_css' => $this->content_css,
                'template' => $this->template,
                'layout' => $this->layout,
                'status' => $this->status,
                'order' => $this->order,
                'featured_image' => $this->featured_image,
                'meta' => $this->meta,
                'seo' => [
                    'title' => $this->getSeoTitle(),
                    'description' => $this->getSeoDescription(),
                    'keywords' => $this->getSeoKeywords(),
                ],
                'url' => $this->getUrl(),
                'breadcrumbs' => $this->getBreadcrumbs(),
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
                'parent' => $this->when($this->relationLoaded('parent') && $this->parent, function () {
                    return [
                        'data' => [
                            'type' => 'pages',
                            'id' => $this->parent->id,
                            'attributes' => [
                                'title' => $this->parent->title,
                                'slug' => $this->parent->slug,
                            ],
                        ],
                    ];
                }),
                'children' => $this->when($this->relationLoaded('children') && $this->children->isNotEmpty(), function () {
                    return [
                        'data' => $this->children->map(fn ($child) => [
                            'type' => 'pages',
                            'id' => $child->id,
                            'attributes' => [
                                'title' => $child->title,
                                'slug' => $child->slug,
                                'status' => $child->status,
                            ],
                        ]),
                    ];
                }),
            ],
        ];
    }
}
