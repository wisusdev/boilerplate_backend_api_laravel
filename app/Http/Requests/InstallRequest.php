<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Datos del primer administrador + configuración básica del sitio.
     */
    public function rules(): array
    {
        return [
            // Administrador fundador
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],

            // Datos del sitio (opcionales; usan los valores por defecto si se omiten)
            'site_name'     => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'currency'      => ['nullable', 'string', 'max:8'],
            'timezone'      => ['nullable', 'timezone'],
        ];
    }
}
