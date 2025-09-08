<?php

namespace App\Http\Requests;

use App\Rules\UniqueSlugForBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variationId = $this->route('variation') ? $this->route('variation')->id : null;
        $businessId = $this->input('data.attributes.business_id') ?? $this->route('business')->id;

        return [
            'data' => 'required|array',
            'data.type' => 'required|string|in:variations',
            'data.attributes' => 'required|array',
            
            // Campos obligatorios
            'data.attributes.product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],
            'data.attributes.name' => [
                'required',
                'string',
                'max:191'
            ],
            'data.attributes.slug' => [
                'sometimes',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                new UniqueSlugForBusiness('variations', $businessId, $variationId)
            ],
            
            // SKU único por negocio
            'data.attributes.sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('variations', 'sku')
                    ->where('business_id', $businessId)
                    ->ignore($variationId)
            ],
            
            // Precios y cantidades
            'data.attributes.price' => 'sometimes|numeric|min:0|max:999999.99',
            'data.attributes.cost_price' => 'sometimes|numeric|min:0|max:999999.99',
            'data.attributes.quantity' => 'sometimes|numeric|min:0|max:999999',
            'data.attributes.min_quantity' => 'sometimes|numeric|min:0|max:999999',
            
            // Características físicas
            'data.attributes.weight' => 'nullable|numeric|min:0|max:999999.999',
            'data.attributes.dimensions' => 'nullable|string|max:191',
            'data.attributes.color' => 'nullable|string|max:50',
            'data.attributes.size' => 'nullable|string|max:50',
            'data.attributes.material' => 'nullable|string|max:100',
            
            // Tipo y descripción
            'data.attributes.type' => 'sometimes|string|in:simple,variable,digital,service',
            'data.attributes.description' => 'nullable|string|max:65535',
            
            // Estado y orden
            'data.attributes.is_active' => 'sometimes|boolean',
            'data.attributes.sort_order' => 'sometimes|integer|min:0',
            
            // Meta data JSON
            'data.attributes.meta_data' => 'nullable|json',
            
            // Relaciones
            'data.relationships' => 'sometimes|array',
            'data.relationships.product' => 'sometimes|array',
            'data.relationships.product.data' => 'sometimes|array',
            'data.relationships.product.data.type' => 'sometimes|string|in:products',
            'data.relationships.product.data.id' => 'sometimes|string',
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'Los datos son requeridos.',
            'data.type.required' => 'El tipo de dato es requerido.',
            'data.type.in' => 'El tipo debe ser "variations".',
            'data.attributes.required' => 'Los atributos son requeridos.',
            
            'data.attributes.product_id.required' => 'El ID del producto es requerido.',
            'data.attributes.product_id.exists' => 'El producto seleccionado no existe.',
            
            'data.attributes.name.required' => 'El nombre de la variación es requerido.',
            'data.attributes.name.max' => 'El nombre no puede exceder los 191 caracteres.',
            
            'data.attributes.slug.regex' => 'El slug debe contener solo letras minúsculas, números y guiones.',
            'data.attributes.slug.max' => 'El slug no puede exceder los 191 caracteres.',
            
            'data.attributes.sku.max' => 'El SKU no puede exceder los 100 caracteres.',
            'data.attributes.sku.unique' => 'Ya existe una variación con este SKU en el negocio.',
            
            'data.attributes.price.numeric' => 'El precio debe ser un número.',
            'data.attributes.price.min' => 'El precio debe ser mayor o igual a 0.',
            'data.attributes.price.max' => 'El precio no puede exceder 999999.99.',
            
            'data.attributes.cost_price.numeric' => 'El precio de costo debe ser un número.',
            'data.attributes.cost_price.min' => 'El precio de costo debe ser mayor o igual a 0.',
            'data.attributes.cost_price.max' => 'El precio de costo no puede exceder 999999.99.',
            
            'data.attributes.quantity.numeric' => 'La cantidad debe ser un número.',
            'data.attributes.quantity.min' => 'La cantidad debe ser mayor o igual a 0.',
            'data.attributes.quantity.max' => 'La cantidad no puede exceder 999999.',
            
            'data.attributes.min_quantity.numeric' => 'La cantidad mínima debe ser un número.',
            'data.attributes.min_quantity.min' => 'La cantidad mínima debe ser mayor o igual a 0.',
            'data.attributes.min_quantity.max' => 'La cantidad mínima no puede exceder 999999.',
            
            'data.attributes.weight.numeric' => 'El peso debe ser un número.',
            'data.attributes.weight.min' => 'El peso debe ser mayor o igual a 0.',
            'data.attributes.weight.max' => 'El peso no puede exceder 999999.999.',
            
            'data.attributes.dimensions.max' => 'Las dimensiones no pueden exceder los 191 caracteres.',
            'data.attributes.color.max' => 'El color no puede exceder los 50 caracteres.',
            'data.attributes.size.max' => 'El tamaño no puede exceder los 50 caracteres.',
            'data.attributes.material.max' => 'El material no puede exceder los 100 caracteres.',
            
            'data.attributes.type.in' => 'El tipo debe ser uno de: simple, variable, digital, service.',
            'data.attributes.description.max' => 'La descripción no puede exceder los 65535 caracteres.',
            
            'data.attributes.sort_order.integer' => 'El orden debe ser un número entero.',
            'data.attributes.sort_order.min' => 'El orden debe ser mayor o igual a 0.',
            
            'data.attributes.meta_data.json' => 'Los meta datos deben ser un JSON válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'data.attributes.product_id' => 'producto',
            'data.attributes.name' => 'nombre',
            'data.attributes.slug' => 'slug',
            'data.attributes.sku' => 'SKU',
            'data.attributes.price' => 'precio',
            'data.attributes.cost_price' => 'precio de costo',
            'data.attributes.quantity' => 'cantidad',
            'data.attributes.min_quantity' => 'cantidad mínima',
            'data.attributes.weight' => 'peso',
            'data.attributes.dimensions' => 'dimensiones',
            'data.attributes.color' => 'color',
            'data.attributes.size' => 'tamaño',
            'data.attributes.material' => 'material',
            'data.attributes.type' => 'tipo',
            'data.attributes.description' => 'descripción',
            'data.attributes.is_active' => 'activo',
            'data.attributes.sort_order' => 'orden',
            'data.attributes.meta_data' => 'meta datos',
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

        if (!$this->has('data.attributes.type')) {
            $this->merge([
                'data' => array_merge($this->input('data', []), [
                    'attributes' => array_merge($this->input('data.attributes', []), [
                        'type' => 'simple'
                    ])
                ])
            ]);
        }
    }
}
