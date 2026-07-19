<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductReviewRequest extends FormRequest
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

        return [
            'data'                              => ['required', 'array'],
            'data.attributes'                   => ['required', 'array'],
            'data.type'                         => ['required', 'string', 'in:product_reviews'],
            // En alta se requiere identificar el producto; en edición no cambia.
            'data.attributes.reviewable_type'   => [$isPatch ? 'prohibited' : 'required', 'string', 'in:tour,transport'],
            'data.attributes.reviewable_id'     => [$isPatch ? 'prohibited' : 'required', 'integer'],
            'data.attributes.rating'            => [$isPatch ? 'sometimes' : 'required', 'integer', 'min:1', 'max:5'],
            'data.attributes.comment'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Campos de moderación (solo admin; el controlador ignora estos de un cliente).
            'data.attributes.is_approved'       => ['sometimes', 'boolean'],
            'data.attributes.admin_reply'       => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
