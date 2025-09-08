<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsPayrollGroup extends Model
{
    use HasFactory;

    public function essentials_payroll_group_transactions()
    {
        return $this->hasMany(EssentialsPayrollGroupTransaction::class, 'payroll_group_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'name' => 'string',
            'status' => 'string',
            'payment_status' => 'string',
            'gross_total' => 'decimal:4',
            'created_by' => 'integer'
        ];
    }
}
