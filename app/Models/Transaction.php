<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    public function transaction_sell_lines()
    {
        return $this->hasMany(TransactionSellLine::class, 'transaction_id');
    }

    public function transaction_payments()
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id');
    }

    public function stock_adjustment_lines()
    {
        return $this->hasMany(StockAdjustmentLine::class, 'transaction_id');
    }

    public function purchase_lines()
    {
        return $this->hasMany(PurchaseLine::class, 'transaction_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'res_table_id' => 'integer',
            'res_waiter_id' => 'integer',
            'type' => 'string',
            'sub_type' => 'string',
            'status' => 'string',
            'sub_status' => 'string',
            'is_quotation' => 'boolean',
            'contact_id' => 'integer',
            'customer_group_id' => 'integer',
            'invoice_no' => 'string',
            'ref_no' => 'string',
            'source' => 'string',
            'subscription_no' => 'string',
            'subscription_repeat_on' => 'string',
            'transaction_date' => 'datetime',
            'total_before_tax' => 'decimal:4',
            'tax_id' => 'integer',
            'tax_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'rp_redeemed' => 'integer',
            'rp_redeemed_amount' => 'decimal:4',
            'shipping_details' => 'string',
            'delivery_date' => 'datetime',
            'shipping_status' => 'string',
            'delivered_to' => 'string',
            'shipping_charges' => 'decimal:4',
            'shipping_custom_field_1' => 'string',
            'shipping_custom_field_2' => 'string',
            'shipping_custom_field_3' => 'string',
            'shipping_custom_field_4' => 'string',
            'shipping_custom_field_5' => 'string',
            'is_export' => 'boolean',
            'round_off_amount' => 'decimal:4',
            'additional_expense_key_1' => 'string',
            'additional_expense_value_1' => 'decimal:4',
            'additional_expense_key_2' => 'string',
            'additional_expense_value_2' => 'decimal:4',
            'additional_expense_key_3' => 'string',
            'additional_expense_value_3' => 'decimal:4',
            'additional_expense_key_4' => 'string',
            'additional_expense_value_4' => 'decimal:4',
            'final_total' => 'decimal:4',
            'expense_category_id' => 'integer',
            'expense_sub_category_id' => 'integer',
            'expense_for' => 'integer',
            'commission_agent' => 'integer',
            'document' => 'string',
            'is_direct_sale' => 'boolean',
            'is_suspend' => 'boolean',
            'exchange_rate' => 'decimal:3',
            'total_amount_recovered' => 'decimal:4',
            'transfer_parent_id' => 'integer',
            'return_parent_id' => 'integer',
            'opening_stock_product_id' => 'integer',
            'created_by' => 'integer',
            'prefer_payment_method' => 'string',
            'prefer_payment_account' => 'integer',
            'custom_field_1' => 'string',
            'custom_field_2' => 'string',
            'custom_field_3' => 'string',
            'custom_field_4' => 'string',
            'import_batch' => 'integer',
            'import_time' => 'datetime',
            'types_of_service_id' => 'integer',
            'packing_charge' => 'decimal:4',
            'is_created_from_api' => 'boolean',
            'essentials_duration' => 'decimal:2',
            'essentials_duration_unit' => 'string',
            'essentials_amount_per_unit_duration' => 'decimal:4',
            'rp_earned' => 'integer',
            'is_recurring' => 'boolean',
            'recur_interval' => 'double',
            'recur_repetitions' => 'integer',
            'recur_stopped_on' => 'datetime',
            'recur_parent_id' => 'integer',
            'invoice_token' => 'string',
            'pay_term_number' => 'integer',
            'selling_price_group_id' => 'integer',
            'is_kitchen_order' => 'boolean'
        ];
    }
}
