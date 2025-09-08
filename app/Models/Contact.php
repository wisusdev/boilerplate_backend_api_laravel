<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'contact_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'contact_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'type' => 'string',
            'contact_type' => 'string',
            'supplier_business_name' => 'string',
            'name' => 'string',
            'prefix' => 'string',
            'first_name' => 'string',
            'middle_name' => 'string',
            'last_name' => 'string',
            'email' => 'string',
            'contact_id' => 'string',
            'contact_status' => 'string',
            'tax_number' => 'string',
            'city' => 'string',
            'state' => 'string',
            'country' => 'string',
            'zip_code' => 'string',
            'dob' => 'date',
            'mobile' => 'string',
            'landline' => 'string',
            'alternate_number' => 'string',
            'pay_term_number' => 'integer',
            'credit_limit' => 'decimal:4',
            'created_by' => 'integer',
            'balance' => 'decimal:4',
            'total_rp' => 'integer',
            'total_rp_used' => 'integer',
            'total_rp_expired' => 'integer',
            'is_default' => 'boolean',
            'customer_group_id' => 'integer',
            'position' => 'string',
            'is_export' => 'boolean',
            'export_custom_field_1' => 'string',
            'export_custom_field_2' => 'string',
            'export_custom_field_3' => 'string',
            'export_custom_field_4' => 'string',
            'export_custom_field_5' => 'string',
            'export_custom_field_6' => 'string',
            'custom_field1' => 'string',
            'custom_field2' => 'string',
            'custom_field3' => 'string',
            'custom_field4' => 'string',
            'custom_field5' => 'string',
            'custom_field6' => 'string',
            'custom_field7' => 'string',
            'custom_field8' => 'string',
            'custom_field9' => 'string',
            'custom_field10' => 'string',
            'deleted_at' => 'timestamp'
        ];
    }
}
