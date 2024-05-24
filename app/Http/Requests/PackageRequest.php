<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:packages'],
            'data.attributes.name' => ['required', 'string', 'max:255'],
            'data.attributes.description' => ['required', 'string'],
            'data.attributes.max_users' => ['required', 'integer'],
            'data.attributes.interval' => ['required', 'string'],
            'data.attributes.interval_count' => ['required', 'integer'],
            'data.attributes.price' => ['required', 'numeric'],
            'data.attributes.trial_days' => ['required', 'integer'],
            'data.attributes.active' => ['required', 'boolean'],
            'data.attributes.created_by' => ['required', 'uuid', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'The data field is required.',
            'data.array' => 'The data field must be an array.',
            'data.type.required' => 'The data.type field is required.',
            'data.type.string' => 'The data.type field must be a string.',
            'data.type.in' => 'The data.type field must be one of: :values.',
            'data.attributes.name.required' => 'The data.attributes.name field is required.',
            'data.attributes.name.string' => 'The data.attributes.name field must be a string.',
            'data.attributes.name.max' => 'The data.attributes.name field must not exceed 255 characters.',
            'data.attributes.description.required' => 'The data.attributes.description field is required.',
            'data.attributes.description.string' => 'The data.attributes.description field must be a string.',
            'data.attributes.max_users.required' => 'The data.attributes.max_users field is required.',
            'data.attributes.max_users.integer' => 'The data.attributes.max_users field must be an integer.',
            'data.attributes.interval.required' => 'The data.attributes.interval field is required.',
            'data.attributes.interval.string' => 'The data.attributes.interval field must be a string.',
            'data.attributes.interval.in' => 'The data.attributes.interval field must be one of: :values.',
            'data.attributes.interval_count.required' => 'The data.attributes.interval_count field is required.',
            'data.attributes.interval_count.integer' => 'The data.attributes.interval_count field must be an integer.',
            'data.attributes.price.required' => 'The data.attributes.price field is required.',
            'data.attributes.price.numeric' => 'The data.attributes.price field must be a number.',
            'data.attributes.trial_days.required' => 'The data.attributes.trial_days field is required.',
            'data.attributes.trial_days.integer' => 'The data.attributes.trial_days field must be an integer.',
            'data.attributes.active.required' => 'The data.attributes.active field is required.',
            'data.attributes.active.boolean' => 'The data.attributes.active field must be a boolean.',
            'data.attributes.created_by.required' => 'The data.attributes.created_by field is required.',
            'data.attributes.created_by.uuid' => 'The data.attributes.created_by field must be a valid UUID.',
            'data.attributes.created_by.exists' => 'The selected data.attributes.created_by is invalid.',
        ];
    }
}
