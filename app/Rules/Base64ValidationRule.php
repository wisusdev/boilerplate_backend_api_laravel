<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64ValidationRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(base64_encode(base64_decode($value, true)) !== $value) {
            $fail(__('validation.base64', ['attribute' => $attribute]));
        }
    }
}
