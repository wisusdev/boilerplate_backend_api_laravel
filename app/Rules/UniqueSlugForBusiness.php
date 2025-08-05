<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueSlugForBusiness implements ValidationRule
{
    protected string $table;
    protected ?int $businessId;
    protected ?int $ignoreId;

    public function __construct(string $table, ?int $businessId = null, ?int $ignoreId = null)
    {
        $this->table = $table;
        $this->businessId = $businessId;
        $this->ignoreId = $ignoreId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || empty($this->businessId)) {
            return;
        }

        $query = DB::table($this->table)
            ->where('slug', $value)
            ->where('business_id', $this->businessId);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('Ya existe un registro con este slug en el negocio.');
        }
    }
}
