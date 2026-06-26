<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isPatch = $this->isMethod('PATCH');
        $required = $isPatch ? 'sometimes' : 'required';

        return [
            'data'                            => ['required', 'array'],
            'data.attributes'                 => ['required', 'array'],
            'data.type'                       => ['required', 'string', 'in:tours'],
            'data.attributes.title'           => [$required, 'string', 'max:255'],
            'data.attributes.description'     => [$required, 'string'],
            'data.attributes.price'           => [$required, 'numeric', 'min:0'],
            'data.attributes.max_capacity'    => [$required, 'integer', 'min:1'],
            'data.attributes.location'        => [$required, 'string', 'max:255'],
            'data.attributes.category_id'     => ['sometimes', 'nullable', 'integer', 'exists:tour_categories,id'],
            'data.attributes.currency_code'   => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'data.attributes.itinerary'       => ['sometimes', 'array'],
            'data.attributes.highlights'      => ['sometimes', 'array'],
            'data.attributes.map_url'         => ['sometimes', 'nullable', 'string', 'max:2048'],
            'data.attributes.map_markers'      => ['sometimes', 'nullable', 'array'],
            'data.attributes.map_markers.*.lat'   => ['required_with:data.attributes.map_markers', 'numeric', 'between:-90,90'],
            'data.attributes.map_markers.*.lng'   => ['required_with:data.attributes.map_markers', 'numeric', 'between:-180,180'],
            'data.attributes.map_markers.*.label' => ['sometimes', 'nullable', 'string', 'max:200'],
            'data.attributes.faqs'            => ['sometimes', 'array'],
            'data.attributes.is_active'       => ['sometimes', 'boolean'],
        ];
    }
}