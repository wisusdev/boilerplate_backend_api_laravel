<?php

namespace App\Http\Requests;

use App\Models\MapPin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MapPinRequest extends FormRequest
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
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:map-pins'],
            'data.attributes' => ['required', 'array'],

            'data.attributes.title' => [$required, 'string', 'max:150'],
            'data.attributes.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'data.attributes.latitude' => [$required, 'numeric', 'between:-90,90'],
            'data.attributes.longitude' => [$required, 'numeric', 'between:-180,180'],
            'data.attributes.icon' => ['sometimes', 'string', Rule::in(MapPin::ICONS)],
            'data.attributes.color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'data.attributes.tour_id' => ['sometimes', 'nullable', 'integer', 'exists:tours,id'],
            'data.attributes.instagram_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Solo http(s): un enlace "javascript:" en el mapa público sería un XSS.
            'data.attributes.link_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'data.attributes.link_label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'data.attributes.is_active' => ['sometimes', 'boolean'],
            'data.attributes.sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $url = $this->input('data.attributes.instagram_url');
            if (is_string($url) && trim($url) !== '' && MapPin::parseInstagram($url) === null) {
                $v->errors()->add(
                    'data.attributes.instagram_url',
                    'El enlace de Instagram debe ser un post, reel o video (instagram.com/p/…, /reel/… o /tv/…). Para otro tipo de enlace usa "Enlace".',
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function pinAttributes(): array
    {
        return (array) $this->validated()['data']['attributes'];
    }
}
