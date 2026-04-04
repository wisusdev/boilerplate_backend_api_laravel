<?php

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add proper authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'private'])],
            'is_featured' => ['nullable', 'boolean'],
            'allow_comments' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['uuid', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'meta' => ['nullable', 'array'],
            'meta.seo_title' => ['nullable', 'string', 'max:70'],
            'meta.seo_description' => ['nullable', 'string', 'max:160'],
            'meta.seo_keywords' => ['nullable', 'string', 'max:255'],
            'meta.og_image' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
