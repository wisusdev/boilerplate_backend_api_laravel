<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    use HasFactory;

    public function cash_register()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    protected function casts(): array
    {
        return [
            'cash_register_id' => 'integer',
            'amount' => 'decimal:4',
            'pay_method' => 'string',
            'transaction_type' => 'string',
            'transaction_id' => 'integer'
        ];
    }
}
