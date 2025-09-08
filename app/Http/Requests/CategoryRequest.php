<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $categoryId = $isUpdate ? $this->route('category')->id : null;

        return [
            'data' => 'required|array',
            'data.type' => 'required|string|in:categories',
            'data.attributes' => 'required|array',

            // Atributos principales
            'data.attributes.name' => 'required|string|max:191',
            'data.attributes.business_id' => ['required', 'integer', Rule::exists('business', 'id')],
            'data.attributes.short_code' => 'nullable|string|max:10',
            'data.attributes.parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'data.attributes.category_type' => ['required', 'string', Rule::in(['product', 'service', 'expense'])],
            'data.attributes.description' => 'nullable|string|max:1000',
            'data.attributes.slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')
                    ->where('business_id', $this->input('data.attributes.business_id'))
                    ->ignore($categoryId)
            ],
            'data.attributes.created_by' => ['nullable', 'integer', Rule::exists('users', 'id')],

            // Relaciones
            'data.relationships' => 'sometimes|array',
            'data.relationships.business' => 'sometimes|array',
            'data.relationships.business.data' => 'sometimes|array',
            'data.relationships.business.data.type' => 'sometimes|string|in:businesses',
            'data.relationships.business.data.id' => 'sometimes|string',

            'data.relationships.parent' => 'sometimes|array',
            'data.relationships.parent.data' => 'sometimes|nullable|array',
            'data.relationships.parent.data.type' => 'sometimes|string|in:categories',
            'data.relationships.parent.data.id' => 'sometimes|string',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'data.attributes.name' => 'nombre',
            'data.attributes.business_id' => 'ID del negocio',
            'data.attributes.short_code' => 'código corto',
            'data.attributes.parent_id' => 'categoría padre',
            'data.attributes.category_type' => 'tipo de categoría',
            'data.attributes.description' => 'descripción',
            'data.attributes.slug' => 'slug',
            'data.attributes.created_by' => 'creado por',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data.attributes.name.required' => 'El nombre de la categoría es obligatorio.',
            'data.attributes.name.max' => 'El nombre no puede tener más de 191 caracteres.',
            'data.attributes.business_id.required' => 'El ID del negocio es obligatorio.',
            'data.attributes.business_id.exists' => 'El negocio especificado no existe.',
            'data.attributes.parent_id.exists' => 'La categoría padre especificada no existe.',
            'data.attributes.category_type.required' => 'El tipo de categoría es obligatorio.',
            'data.attributes.category_type.in' => 'El tipo de categoría debe ser product, service o expense.',
            'data.attributes.slug.unique' => 'Ya existe una categoría con este slug en el negocio.',
            'data.attributes.slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'data.attributes.created_by.required' => 'El usuario creador es obligatorio.',
            'data.attributes.created_by.exists' => 'El usuario especificado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si es una actualización y no se proporciona created_by, usar el usuario autenticado
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            if (!$this->has('data.attributes.created_by')) {
                $this->merge([
                    'data' => array_merge($this->input('data', []), [
                        'attributes' => array_merge($this->input('data.attributes', []), [
                            'created_by' => auth()->id()
                        ])
                    ])
                ]);
            }
        } else {
            // Para creación, siempre usar el usuario autenticado
            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'created_by' => auth()->id()
                    ])
                ])
            ]);
        }

        // Generar slug automáticamente si no se proporciona
        if (!$this->has('data.attributes.slug') && $this->has('data.attributes.name')) {
            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'slug' => str()->slug($this->input('data.attributes.name'))
                    ])
                ])
            ]);
        }
    }
}
