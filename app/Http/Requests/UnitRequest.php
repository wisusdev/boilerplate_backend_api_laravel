<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
	    $businessId = $this->input('data.attributes.business_id');
		$unitId = $this->route('unit') ? $this->route('unit')->id : null;

        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:units',
            'data.attributes' => 'required|array',
            'data.attributes.is_active' => 'boolean',
            'data.attributes.name' => ['required', 'string', 'max:191', Rule::unique("units", "name")->where("business_id", $businessId)->ignore($unitId)],
            'data.attributes.short_name' => ['required', 'string', 'max:191', Rule::unique("units", "short_name")->where("business_id", $businessId)->ignore($unitId)],
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
            $data['data.attributes.business_id'] = 'prohibited';
            $data['data.attributes.created_by'] = 'sometimes|integer|exists:users,id';
        }

        return $data;
    }

}
