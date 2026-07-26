<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPatch = $this->isMethod('PATCH');
        $required = $isPatch ? 'sometimes' : 'required';

        $categoryId = $this->route('expenseCategory')?->id;

        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:expense-categories'],
            'data.attributes.name' => [$required, 'string', 'max:100'],
            'data.attributes.slug' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('expense_categories', 'slug')->ignore($categoryId)],
            'data.attributes.icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            'data.attributes.is_active' => ['sometimes', 'boolean'],
            'data.attributes.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
