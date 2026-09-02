<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reordenar la galería aceptaba cualquier array sin comprobar tamaño ni tipo:
 * un cliente (o una integración rota) podía mandar miles de ids y el
 * controlador los recorría uno por uno, cada uno con su propia consulta.
 *
 * No se exige que cada id exista: si otro admin borró una foto justo antes de
 * que este reordenamiento llegue, ese id concreto simplemente no actualiza
 * nada (`GalleryItem::where('id', $id)`) — es más razonable que rechazar todo
 * el reordenamiento por una carrera benigna entre dos personas editando a la vez.
 */
class GalleryReorderRequest extends FormRequest
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
        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.ids' => ['required', 'array', 'min:1', 'max:500'],
            // Los ids de galería son UUID (string), no enteros.
            'data.attributes.ids.*' => ['string', 'max:64', 'distinct'],
        ];
    }
}
