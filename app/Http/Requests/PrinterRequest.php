<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'data' => 'required|array',
            'data.type' => 'required|in:printers',
            'data.attributes' => 'required|array',
            'data.attributes.name' => 'required|string|max:191',
            'data.attributes.connection_type' => 'required|in:network,windows,linux',
            'data.attributes.capability_profile' => 'required|in:default,simple,SP2000,TEP-200M,P822D',
            'data.attributes.char_per_line' => 'nullable|string|max:191',
            'data.attributes.ip_address' => 'nullable|string|max:191',
            'data.attributes.port' => 'nullable|string|max:191',
            'data.attributes.path' => 'nullable|string|max:191',
        ];

        if ($this->isMethod('post')) {
            $rules['data.attributes.business_id'] = 'required|integer|exists:businesses,id';
            $rules['data.attributes.created_by'] = 'required|integer|exists:users,id';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['data.id'] = 'required|integer|exists:printers,id';
            $rules['data.attributes.business_id'] = 'prohibited';
            $rules['data.attributes.created_by'] = 'sometimes|integer|exists:users,id';
        }

        return $rules;
    }
}
