<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'amount' => 'decimal:4',
            'reff_no' => 'string',
            'operation_date' => 'datetime',
            'created_by' => 'integer',
            'transaction_id' => 'integer',
            'transaction_payment_id' => 'integer',
            'transfer_transaction_id' => 'integer',
            'deleted_at' => 'timestamp'
        ];
    }
}
