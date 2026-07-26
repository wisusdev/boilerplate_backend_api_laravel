<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
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

        $couponId = $this->route('coupon')?->id;

        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:coupons'],
            'data.attributes.code' => [$required, 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($couponId)],
            'data.attributes.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.type' => [$required, 'string', Rule::in([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED])],
            'data.attributes.value' => [$required, 'numeric', 'min:0'],
            'data.attributes.max_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.min_pax' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'data.attributes.min_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.applies_to' => ['sometimes', 'string', 'in:all,tour,transport'],
            'data.attributes.usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'data.attributes.per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'data.attributes.starts_at' => ['sometimes', 'nullable', 'date'],
            'data.attributes.expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:data.attributes.starts_at'],
            'data.attributes.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('data.attributes.type') === Coupon::TYPE_PERCENTAGE) {
                $value = $this->input('data.attributes.value');
                if ($value !== null && (float) $value > 100) {
                    $validator->errors()->add('data.attributes.value', 'Un cupón porcentual no puede exceder 100.');
                }
            }
        });
    }
}
