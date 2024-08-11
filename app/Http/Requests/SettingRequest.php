<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
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
        $rules = [
			'data' => ['required', 'array'],
			'data.type' => ['required', 'string', 'exists:settings,key'],
			'data.attributes' => ['required', 'array'],
        ];

		if ($this->input('data.type') === 'app') {
			$rules['data.attributes.name'] = ['required', 'string'];
			$rules['data.attributes.url_api'] = ['required', 'url'];
			$rules['data.attributes.url_frontend'] = ['required', 'url'];
			$rules['data.attributes.description'] = ['string'];
			$rules['data.attributes.logo'] = ['nullable', 'string', 'not_in:'];
			$rules['data.attributes.favicon'] = ['nullable', 'string', 'not_in:'];
			$rules['data.attributes.email'] = ['email'];
			$rules['data.attributes.phone'] = ['string'];
			$rules['data.attributes.address'] = ['required', 'string'];
			$rules['data.attributes.timezone'] = ['required', 'string'];
		}

		return $rules;
    }
}
