<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolRequest extends FormRequest
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
		$role = $this->route('role');

		$rules = [
			'data' => ['required', 'array'],
			'data.type' => ['required', 'string', 'in:roles'],
			'data.attributes' => ['required', 'array'],
			'data.attributes.permissions' => ['array'],
		];

		if ($this->isMethod('put') || $this->isMethod('patch')) {
			$rules['data.attributes.name'] = ['required', 'string', 'max:50', 'min:3', Rule::unique('roles', 'name')->ignore($role->id)];
		}

		if ($this->isMethod('post')) {
			$rules['data.attributes.name'] = ['required', 'string', 'max:50', 'min:3', Rule::unique('roles', 'name')];
		}

		return $rules;
	}

	public function messages(): array
	{
		return [
			'data.attributes.name.required' => 'validation.nameRequired',
			'data.attributes.name.string' => 'validation.nameString',
			'data.attributes.name.max' => 'validation.nameMax',
			'data.attributes.name.min' => 'validation.nameMin',
			'data.attributes.name.unique' => 'validation.nameUnique',
			'data.attributes.permissions.required' => 'validation.permissionsRequired',
			'data.attributes.permissions.array' => 'validation.permissionsArray',
			'data.attributes.role.name.unique' => 'validation.nameUnique',
		];
	}
}
