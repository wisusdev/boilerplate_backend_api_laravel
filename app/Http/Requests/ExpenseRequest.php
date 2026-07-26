<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPatch = $this->isMethod('PATCH');
        $required = $isPatch ? 'sometimes' : 'required';

        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:expenses'],
            // NULL = gasto general (no específico de un tour).
            'data.attributes.tour_id' => ['sometimes', 'nullable', 'integer', 'exists:tours,id'],
            'data.attributes.expense_category_id' => [$required, 'integer', 'exists:expense_categories,id'],
            'data.attributes.guide_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'data.attributes.amount' => [$required, 'numeric', 'min:0'],
            'data.attributes.comment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.spent_at' => ['sometimes', 'nullable', 'date'],
            'data.attributes.currency_code' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
