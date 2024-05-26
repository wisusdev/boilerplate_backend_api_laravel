<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:subscriptions'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.user_id' => ['required', 'exists:users,id'],
            'data.attributes.package_id' => ['required', 'exists:packages,id'],
            'data.attributes.created_by' => ['required', 'exists:users,id'],
            'data.attributes.payment_method' => ['required', 'string'],
            'data.attributes.payment_transaction_id' => ['required', 'string'],
            'data.attributes.status' => ['required', 'string', 'in:approved,waiting,declined,cancel'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.type.in' => 'The data.type must be subscriptions.',
            'data.attributes.user_id.exists' => 'The user_id must be exists in users table.',
            'data.attributes.package_id.exists' => 'The package_id must be exists in packages table.',
            'data.attributes.created_by.exists' => 'The created_by must be exists in users table.',
            'data.attributes.status.in' => 'The status must be one of: approved, waiting, declined, cancel.',
        ];
    }
}
