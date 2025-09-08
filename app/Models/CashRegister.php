<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    public function cash_register_transactions()
    {
        return $this->hasMany(CashRegisterTransaction::class, 'cash_register_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'user_id' => 'integer',
            'closed_at' => 'datetime',
            'closing_amount' => 'decimal:4',
            'total_card_slips' => 'integer',
            'total_cheques' => 'integer'
        ];
    }
}
