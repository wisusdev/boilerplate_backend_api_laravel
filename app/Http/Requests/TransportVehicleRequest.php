<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransportVehicleRequest extends FormRequest
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
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:transport_vehicles'],
            'data.attributes.title' => [$required, 'string', 'max:255'],
            'data.attributes.vehicle_type' => [$required, 'string', 'max:50'],
            'data.attributes.description' => ['sometimes', 'nullable', 'string'],
            'data.attributes.location' => [$required, 'string', 'max:255'],
            'data.attributes.hourly_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.daily_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.capacity' => [$required, 'integer', 'min:1'],
            'data.attributes.features' => ['sometimes', 'array'],
            'data.attributes.is_active' => ['sometimes', 'boolean'],
            'data.attributes.meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
