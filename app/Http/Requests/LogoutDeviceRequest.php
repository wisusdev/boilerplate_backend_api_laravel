<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LogoutDeviceRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:logout-device'],
            'data.attributes.device_id' => ['required', 'string', 'exists:device_infos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.type.required' => 'validation.dataTypeRequired',
            'data.type.in' => 'validation.dataTypeIn',
            'data.type.string' => 'validation.dataTypeString',
            'data.attributes.device_id.required' => 'validation.deviceIdRequired',
            'data.attributes.device_id.string' => 'validation.deviceIdString',
            'data.attributes.device_id.exists' => 'validation.deviceIdExists',
        ];
    }
}
