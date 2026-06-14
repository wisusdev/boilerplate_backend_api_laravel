<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:custom_inquiries'],
            'data.attributes.preferred_destinations' => ['required', 'array', 'min:1'],
            'data.attributes.travel_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'data.attributes.travel_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:data.attributes.travel_start_date'],
            'data.attributes.budget_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.budget_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.travelers_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'data.attributes.currency_code' => ['sometimes', 'string', 'size:3'],
            'data.attributes.message' => ['sometimes', 'nullable', 'string'],
        ];
    }
}