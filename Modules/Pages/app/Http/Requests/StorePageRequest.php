<?php

namespace Modules\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255', 'unique:pages,slug'],
            'content' => ['nullable', 'string'],
            'content_html' => ['nullable', 'string'],
            'content_json' => ['nullable', 'array'],
            'content_css' => ['nullable', 'array'],
            'template' => ['nullable', 'string', 'max:50'],
            'layout' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'private'])],
            'parent_id' => ['nullable', 'uuid', 'exists:pages,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.seo_title' => ['nullable', 'string', 'max:70'],
            'meta.seo_description' => ['nullable', 'string', 'max:160'],
            'meta.seo_keywords' => ['nullable', 'string', 'max:255'],
            'meta.og_image' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
