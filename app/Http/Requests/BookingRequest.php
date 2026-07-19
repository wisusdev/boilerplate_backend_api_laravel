<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
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
        $rules = [
            'data'            => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type'       => ['required', 'string', 'in:bookings'],
        ];

        if ($this->isMethod('post')) {
            $rules['data.attributes.booking_type'] = ['required', 'string', Rule::in([
                Booking::TYPE_TOUR,
                Booking::TYPE_TRANSPORT,
            ])];

            // Tour booking fields
            $rules['data.attributes.tour_id']      = ['required_if:data.attributes.booking_type,tour', 'nullable', 'integer', 'exists:tours,id'];
            $rules['data.attributes.booking_date'] = ['required_if:data.attributes.booking_type,tour', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'];
            $rules['data.attributes.pax_count']    = ['required_if:data.attributes.booking_type,tour', 'nullable', 'integer', 'min:1'];
            // Servicios extra seleccionados (índices de tour.service_fees).
            $rules['data.attributes.service_fees']   = ['sometimes', 'array'];
            $rules['data.attributes.service_fees.*'] = ['integer', 'min:0'];
            // Opción de vehículo (opcional): índice dentro de tour.vehicle_options.
            $rules['data.attributes.upgrade_option_index'] = ['sometimes', 'nullable', 'integer', 'min:0'];
            // Punto de recogida (dirección + marcador opcional en el mapa).
            $rules['data.attributes.pickup_address'] = ['sometimes', 'nullable', 'string', 'max:500'];
            // Sin 'sometimes': así el required_with se evalúa aunque el par venga incompleto.
            $rules['data.attributes.pickup_lat']     = ['nullable', 'numeric', 'between:-90,90', 'required_with:data.attributes.pickup_lng'];
            $rules['data.attributes.pickup_lng']     = ['nullable', 'numeric', 'between:-180,180', 'required_with:data.attributes.pickup_lat'];

            // Transport booking fields
            $rules['data.attributes.transport_vehicle_id'] = ['required_if:data.attributes.booking_type,transport', 'nullable', 'integer', 'exists:transport_vehicles,id'];
            $rules['data.attributes.pickup_at']            = ['required_if:data.attributes.booking_type,transport', 'nullable', 'date_format:Y-m-d H:i:s'];
            $rules['data.attributes.dropoff_at']           = ['required_if:data.attributes.booking_type,transport', 'nullable', 'date_format:Y-m-d H:i:s', 'after:data.attributes.pickup_at'];
            $rules['data.attributes.pickup_location']      = ['required_if:data.attributes.booking_type,transport', 'nullable', 'string', 'max:255'];
            $rules['data.attributes.dropoff_location']     = ['required_if:data.attributes.booking_type,transport', 'nullable', 'string', 'max:255'];
            $rules['data.attributes.rental_type']          = ['required_if:data.attributes.booking_type,transport', 'nullable', 'string', Rule::in(['hourly', 'daily'])];
            $rules['data.attributes.quantity']             = ['sometimes', 'integer', 'min:1'];
            $rules['data.attributes.currency_code']        = ['sometimes', 'string', 'size:3'];
            $rules['data.attributes.notes']                = ['sometimes', 'nullable', 'string'];
            // Cupón de descuento (opcional, tour o transporte).
            $rules['data.attributes.coupon_code']          = ['sometimes', 'nullable', 'string', 'max:60'];
        } else {
            $rules['data.attributes.status'] = ['required', 'string', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CANCELLED,
            ])];
        }

        return $rules;
    }
}
