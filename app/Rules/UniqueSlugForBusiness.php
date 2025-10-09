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
	protected string $whereNull;

    public function __construct(string $table, ?int $businessId = null, ?int $ignoreId = null, $ignore = null, $whereNull = 'deleted_at')
    {
        $this->table = $table;
        $this->businessId = $businessId;
        $this->ignoreId = $ignoreId;
		$this->whereNull = $whereNull;
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

		if ($this->whereNull) {
			$query->whereNull($this->whereNull);
		}

        if ($query->exists()) {
            $fail('Ya existe un registro con este slug en el negocio.');
        }
    }
}
