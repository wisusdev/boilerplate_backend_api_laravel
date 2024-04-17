<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.attributes.device_id' => ['required', 'string', 'exists:device_infos,id'],
        ];
    }

    public function messages()
    {
        return [
            'data.attributes.device_id.required' => 'Device ID is required',
            'data.attributes.device_id.string' => 'Device ID must be a string',
            'data.attributes.device_id.exists' => 'Device ID does not exist',
        ];
    }
}
