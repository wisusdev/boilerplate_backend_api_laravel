<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionPayment extends Model
{
    use HasFactory;

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    protected function casts(): array
    {
        return [
            'transaction_id' => 'integer',
            'business_id' => 'integer',
            'is_return' => 'boolean',
            'amount' => 'decimal:4',
            'method' => 'string',
            'transaction_no' => 'string',
            'payment_type' => 'string',
            'card_transaction_number' => 'string',
            'card_number' => 'string',
            'card_type' => 'string',
            'card_holder_name' => 'string',
            'card_month' => 'string',
            'card_year' => 'string',
            'card_security' => 'string',
            'cheque_number' => 'string',
            'bank_account_number' => 'string',
            'paid_on' => 'datetime',
            'created_by' => 'integer',
            'paid_through_link' => 'boolean',
            'gateway' => 'string',
            'is_advance' => 'boolean',
            'payment_for' => 'integer',
            'parent_id' => 'integer',
            'note' => 'string',
            'document' => 'string',
            'payment_ref_no' => 'string',
            'account_id' => 'integer'
        ];
    }
}
