<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TourRequest extends FormRequest
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
            'data'                            => ['required', 'array'],
            'data.attributes'                 => ['required', 'array'],
            'data.type'                       => ['required', 'string', 'in:tours'],
            'data.attributes.title'           => [$required, 'string', 'max:255'],
            'data.attributes.description'     => [$required, 'string'],
            'data.attributes.price'           => [$required, 'numeric', 'min:0'],
            'data.attributes.sale_price'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data.attributes.child_price'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // Precios escalonados por nº de pasajeros: [{min_pax, discount_percent}]
            'data.attributes.pricing_tiers'                    => ['sometimes', 'nullable', 'array'],
            'data.attributes.pricing_tiers.*.min_pax'          => ['required_with:data.attributes.pricing_tiers', 'integer', 'min:1'],
            'data.attributes.pricing_tiers.*.discount_percent' => ['required_with:data.attributes.pricing_tiers', 'numeric', 'min:0', 'max:100'],
            // Opciones de vehículo de paga (hasta 3): [{name, surcharge}]
            'data.attributes.vehicle_options'             => ['sometimes', 'nullable', 'array', 'max:3'],
            'data.attributes.vehicle_options.*.name'      => ['required_with:data.attributes.vehicle_options', 'string', 'max:120'],
            'data.attributes.vehicle_options.*.surcharge' => ['required_with:data.attributes.vehicle_options', 'numeric', 'min:0'],
            // Visibilidad de secciones del flujo de reserva
            'data.attributes.booking_sections'         => ['sometimes', 'nullable', 'array'],
            'data.attributes.booking_sections.vehicle' => ['sometimes', 'boolean'],
            'data.attributes.booking_sections.pickup'  => ['sometimes', 'boolean'],
            'data.attributes.booking_sections.coupon'  => ['sometimes', 'boolean'],
            'data.attributes.booking_sections.fare'    => ['sometimes', 'boolean'],
            'data.attributes.duration_days'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'data.attributes.duration_nights' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'data.attributes.min_advance_days'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'data.attributes.cancellation_hours'  => ['sometimes', 'nullable', 'integer', 'min:0', 'max:8760'],
            'data.attributes.max_capacity'    => [$required, 'integer', 'min:1'],
            'data.attributes.location'        => [$required, 'string', 'max:255'],
            'data.attributes.category_id'     => ['sometimes', 'nullable', 'integer', 'exists:tour_categories,id'],
            'data.attributes.currency_code'   => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'data.attributes.itinerary'       => ['sometimes', 'array'],
            'data.attributes.highlights'      => ['sometimes', 'array'],
            'data.attributes.includes'        => ['sometimes', 'array'],
            'data.attributes.includes.*'      => ['string', 'max:255'],
            'data.attributes.excludes'        => ['sometimes', 'array'],
            'data.attributes.excludes.*'      => ['string', 'max:255'],
            'data.attributes.service_fees'          => ['sometimes', 'array'],
            'data.attributes.service_fees.*.name'   => ['required_with:data.attributes.service_fees', 'string', 'max:120'],
            'data.attributes.service_fees.*.amount' => ['required_with:data.attributes.service_fees', 'numeric', 'min:0'],
            'data.attributes.service_fees.*.calc'   => ['sometimes', 'string', 'in:fixed,per_person'],
            'data.attributes.is_featured'     => ['sometimes', 'boolean'],
            'data.attributes.meta_title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'data.attributes.map_url'         => ['sometimes', 'nullable', 'string', 'max:2048'],
            'data.attributes.map_markers'      => ['sometimes', 'nullable', 'array'],
            'data.attributes.map_markers.*.lat'   => ['required_with:data.attributes.map_markers', 'numeric', 'between:-90,90'],
            'data.attributes.map_markers.*.lng'   => ['required_with:data.attributes.map_markers', 'numeric', 'between:-180,180'],
            'data.attributes.map_markers.*.label' => ['sometimes', 'nullable', 'string', 'max:200'],
            'data.attributes.faqs'            => ['sometimes', 'array'],
            'data.attributes.is_active'       => ['sometimes', 'boolean'],
        ];
    }
}