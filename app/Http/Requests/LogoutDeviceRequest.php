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
            'data.attributes.device_id' => ['required', 'string', 'exists:device_infos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.attributes.device_id.required' => 'validation.deviceIdIsRequired',
            'data.attributes.device_id.string' => 'validation.deviceIdMustBeString',
            'data.attributes.device_id.exists' => 'validation.deviceIdNotExists',
        ];
    }
}
