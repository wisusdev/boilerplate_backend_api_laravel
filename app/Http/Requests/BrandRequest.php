<?php

namespace App\Http\Requests;

use App\Rules\UniqueSlugForBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand') ? $this->route('brand')->id : null;
        $businessId = $this->input('data.attributes.business_id');

        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:brands',
            'data.attributes' => 'required|array',

            // Campos obligatorios
            'data.attributes.name' => ['required', 'string', 'max:191', Rule::unique('brands', 'name')->where('business_id', $businessId)->ignore($brandId)],
            'data.attributes.slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', new UniqueSlugForBusiness('brands', $businessId, $brandId)],

            // Campos opcionales
            'data.attributes.description' => 'nullable|string|max:65535',
            'data.attributes.logo' => 'nullable|string|max:191',
            'data.attributes.website' => 'nullable|url|max:191',
            'data.attributes.email' => 'nullable|email|max:191',
            'data.attributes.phone' => 'nullable|string|max:20',
            'data.attributes.address' => 'nullable|string|max:500',
            'data.attributes.is_active' => 'sometimes|boolean',
            'data.attributes.sort_order' => 'sometimes|integer|min:0',
            'data.attributes.meta_title' => 'nullable|string|max:191',
            'data.attributes.meta_description' => 'nullable|string|max:500',
            'data.attributes.meta_keywords' => 'nullable|string|max:500',

            // Relaciones
            'data.relationships' => 'sometimes|array',
            'data.relationships.business' => 'sometimes|array',
            'data.relationships.business.data' => 'sometimes|array',
            'data.relationships.business.data.type' => 'sometimes|string|in:businesses',
            'data.relationships.business.data.id' => 'sometimes|string',
        ];

        if ($this->isMethod('post')) {
            $data['data.attributes.business_id'] = 'required|integer|exists:businesses,id';
            $data['data.attributes.created_by'] = 'required|integer|exists:users,id';
        } else if ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.id'] = 'required|integer|exists:brands,id';
            $data['data.attributes.business_id'] = 'prohibited'; // El ID del negocio no debe actualizarse
            $data['data.attributes.created_by'] = 'sometimes|integer|exists:users,id';
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'data.required' => 'Los datos son requeridos.',
            'data.type.required' => 'El tipo de dato es requerido.',
            'data.type.in' => 'El tipo debe ser "brands".',
            'data.attributes.required' => 'Los atributos son requeridos.',

            'data.attributes.business_id.required' => 'El ID del negocio es requerido.',
            'data.attributes.business_id.exists' => 'El negocio seleccionado no existe.',

            'data.attributes.name.required' => 'El nombre de la marca es requerido.',
            'data.attributes.name.max' => 'El nombre no puede exceder los 191 caracteres.',
            'data.attributes.name.unique' => 'Ya existe una marca con este nombre en el negocio.',

            'data.attributes.slug.regex' => 'El slug debe contener solo letras minúsculas, números y guiones.',
            'data.attributes.slug.max' => 'El slug no puede exceder los 191 caracteres.',

            'data.attributes.description.max' => 'La descripción no puede exceder los 65535 caracteres.',
            'data.attributes.logo.max' => 'La URL del logo no puede exceder los 191 caracteres.',
            'data.attributes.website.url' => 'El sitio web debe ser una URL válida.',
            'data.attributes.website.max' => 'El sitio web no puede exceder los 191 caracteres.',
            'data.attributes.email.email' => 'El email debe tener un formato válido.',
            'data.attributes.email.max' => 'El email no puede exceder los 191 caracteres.',
            'data.attributes.phone.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'data.attributes.address.max' => 'La dirección no puede exceder los 500 caracteres.',
            'data.attributes.sort_order.integer' => 'El orden debe ser un número entero.',
            'data.attributes.sort_order.min' => 'El orden debe ser mayor o igual a 0.',
            'data.attributes.meta_title.max' => 'El meta título no puede exceder los 191 caracteres.',
            'data.attributes.meta_description.max' => 'La meta descripción no puede exceder los 500 caracteres.',
            'data.attributes.meta_keywords.max' => 'Las meta palabras clave no pueden exceder los 500 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'data.attributes.business_id' => 'negocio',
            'data.attributes.name' => 'nombre',
            'data.attributes.slug' => 'slug',
            'data.attributes.description' => 'descripción',
            'data.attributes.logo' => 'logo',
            'data.attributes.website' => 'sitio web',
            'data.attributes.email' => 'email',
            'data.attributes.phone' => 'teléfono',
            'data.attributes.address' => 'dirección',
            'data.attributes.is_active' => 'activo',
            'data.attributes.sort_order' => 'orden',
            'data.attributes.meta_title' => 'meta título',
            'data.attributes.meta_description' => 'meta descripción',
            'data.attributes.meta_keywords' => 'meta palabras clave',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Generar slug automáticamente si no se proporciona
        if (!$this->input('data.attributes.slug') && $this->input('data.attributes.name')) {
            $slug = str($this->input('data.attributes.name'))
                ->lower()
                ->ascii()
                ->replace(' ', '-')
                ->replace('_', '-')
                ->replaceMatches('/[^a-z0-9\-]/', '')
                ->replaceMatches('/-+/', '-')
                ->trim('-')
                ->toString();

            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'slug' => $slug
                    ])
                ])
            ]);
        }

        // Establecer valores por defecto
        if (!$this->has('data.attributes.is_active')) {
            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'is_active' => true
                    ])
                ])
            ]);
        }

        if (!$this->has('data.attributes.sort_order')) {
            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'sort_order' => 0
                    ])
                ])
            ]);
        }
    }
}
