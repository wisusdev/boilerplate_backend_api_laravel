<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'data.type' => ['required', 'string', 'in:products'],
            'data.attributes.name' => ['required', 'string', 'max:191'],
            'data.attributes.type' => ['required', 'string', 'in:single,variable,modifier,combo'],
            'data.attributes.unit_id' => ['required', 'integer', 'exists:units,id'],
            'data.attributes.created_by' => ['required', 'integer', 'exists:users,id'],

            // Campos opcionales básicos
            'data.attributes.sub_unit_ids' => ['nullable', 'string'],
            'data.attributes.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'data.attributes.sub_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'data.attributes.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'data.attributes.tax' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'data.attributes.tax_type' => ['nullable', 'string', 'in:inclusive,exclusive'],
            'data.attributes.enable_stock' => ['boolean'],
            'data.attributes.alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.sku' => ['nullable', 'string', 'max:191'],
            'data.attributes.barcode_type' => ['nullable', 'string', 'in:C39,C128,EAN13,EAN8,UPCA,UPCE'],
            'data.attributes.expiry_period' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.expiry_period_type' => ['nullable', 'string', 'in:days,months,years'],
            'data.attributes.enable_sr_no' => ['boolean'],
            'data.attributes.weight' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_description' => ['nullable', 'string'],
            'data.attributes.image' => ['nullable', 'string'],

            // Campos de precio
            'data.attributes.applicable_tax' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.selling_price' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.selling_price_tax_type' => ['nullable', 'string', 'in:inclusive,exclusive'],

            // Campos personalizados
            'data.attributes.product_custom_field1' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field2' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field3' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field4' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field5' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field6' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field7' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field8' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field9' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field10' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field11' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field12' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field13' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field14' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field15' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field16' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field17' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field18' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field19' => ['nullable', 'string', 'max:191'],
            'data.attributes.product_custom_field20' => ['nullable', 'string', 'max:191'],

            // Campos de ubicación y rack
            'data.attributes.warranty_id' => ['nullable', 'integer'],
            'data.attributes.is_inactive' => ['boolean'],
            'data.attributes.not_for_selling' => ['boolean'],

            // Variaciones (para productos variables)
            'data.attributes.variations' => ['array'],
            'data.attributes.variations.*.name' => ['required_with:data.attributes.variations', 'string', 'max:191'],
            'data.attributes.variations.*.sub_sku' => ['nullable', 'string', 'max:191'],
            'data.attributes.variations.*.default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.variations.*.dpp_inc_tax' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.variations.*.profit_percent' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.variations.*.default_sell_price' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.variations.*.sell_price_inc_tax' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.attributes.business_id'] = 'prohibited'; // El ID del negocio no debe actualizarse

            $product = $this->route('product');
            if (null !== $this->input('data.attributes.sku')) {
                $rules['data.attributes.sku'][] = Rule::unique('products', 'sku')->ignore($product->id)->where('business_id', $product->business_id);
            }
        }

        if ($this->isMethod('post')) {
            $rules['data.attributes.business_id'] = ['required', 'integer', 'exists:businesses,id'];

            // Para creación, validar SKU único por negocio
            if (null !== $this->input('data.attributes.sku')) {
                $rules['data.attributes.sku'][] = Rule::unique('products', 'sku')->where('business_id', $this->input('data.attributes.business_id'));
            }
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data.type.required' => 'El tipo de recurso es requerido.',
            'data.type.in' => 'El tipo de recurso debe ser "products".',
            'data.attributes.name.required' => 'El nombre del producto es requerido.',
            'data.attributes.name.max' => 'El nombre del producto no puede exceder 191 caracteres.',
            'data.attributes.business_id.required' => 'El negocio es requerido.',
            'data.attributes.business_id.exists' => 'El negocio seleccionado no existe.',
            'data.attributes.type.required' => 'El tipo de producto es requerido.',
            'data.attributes.type.in' => 'El tipo de producto debe ser: single, variable, modifier o combo.',
            'data.attributes.unit_id.required' => 'La unidad es requerida.',
            'data.attributes.unit_id.exists' => 'La unidad seleccionada no existe.',
            'data.attributes.category_id.exists' => 'La categoría seleccionada no existe.',
            'data.attributes.sub_category_id.exists' => 'La subcategoría seleccionada no existe.',
            'data.attributes.brand_id.exists' => 'La marca seleccionada no existe.',
            'data.attributes.tax.exists' => 'El impuesto seleccionado no existe.',
            'data.attributes.tax_type.in' => 'El tipo de impuesto debe ser inclusive o exclusive.',
            'data.attributes.alert_quantity.min' => 'La cantidad de alerta no puede ser negativa.',
            'data.attributes.sku.unique' => 'El SKU ya existe para este negocio.',
            'data.attributes.barcode_type.in' => 'Tipo de código de barras inválido.',
            'data.attributes.expiry_period.min' => 'El período de expiración no puede ser negativo.',
            'data.attributes.expiry_period_type.in' => 'El tipo de período de expiración debe ser: days, months o years.',
            'data.attributes.selling_price.min' => 'El precio de venta no puede ser negativo.',
            'data.attributes.selling_price_tax_type.in' => 'El tipo de impuesto del precio de venta debe ser inclusive o exclusive.',
            'data.attributes.variations.*.name.required_with' => 'El nombre de la variación es requerido.',
            'data.attributes.variations.*.default_purchase_price.min' => 'El precio de compra no puede ser negativo.',
            'data.attributes.variations.*.profit_percent.min' => 'El porcentaje de ganancia no puede ser negativo.',
            'data.attributes.variations.*.default_sell_price.min' => 'El precio de venta no puede ser negativo.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validación personalizada para productos variables
            if ($this->input('data.attributes.type') === 'variable') {
                if (!$this->has('data.attributes.variations') || empty($this->input('data.attributes.variations'))) {
                    $validator->errors()->add(
                        'data.attributes.variations',
                        'Los productos variables deben tener al menos una variación.'
                    );
                }
            }

            // Validar que la subcategoría pertenezca a la categoría seleccionada
            if ($this->has('data.attributes.category_id') && $this->has('data.attributes.sub_category_id')) {
                $categoryId = $this->input('data.attributes.category_id');
                $subCategoryId = $this->input('data.attributes.sub_category_id');

                if ($categoryId && $subCategoryId) {
                    $subCategory = \App\Models\Category::find($subCategoryId);
                    if ($subCategory && $subCategory->parent_id !== $categoryId) {
                        $validator->errors()->add(
                            'data.attributes.sub_category_id',
                            'La subcategoría seleccionada no pertenece a la categoría especificada.'
                        );
                    }
                }
            }
        });
    }
}
