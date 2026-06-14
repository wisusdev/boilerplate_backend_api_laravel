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
