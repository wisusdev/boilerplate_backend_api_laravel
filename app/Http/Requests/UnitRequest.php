<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:units',
            'data.attributes' => 'required|array',
            'data.attributes.is_active' => 'boolean',
            'data.attributes.actual_name' => 'required|string|max:191',
            'data.attributes.short_name' => 'required|string|max:191',
            'data.attributes.allow_decimal' => 'required|boolean',
            'data.attributes.base_unit_id' => 'nullable|integer|exists:units,id',
            'data.attributes.base_unit_multiplier' => 'nullable|numeric|min:0',
        ];

        if ($this->isMethod('post')) {
            $data['data.attributes.business_id'] = 'required|integer|exists:businesses,id';
            $data['data.attributes.created_by'] = 'required|integer|exists:users,id';
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.id'] = 'required|integer|exists:units,id';
            $data['data.attributes.business_id'] = 'sometimes|integer|exists:businesses,id';
            $data['data.attributes.created_by'] = 'sometimes|integer|exists:users,id';
        }

        return $data;
    }
}
