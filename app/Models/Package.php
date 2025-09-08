<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'location_count' => 'integer',
            'user_count' => 'integer',
            'product_count' => 'integer',
            'bookings' => 'boolean',
            'kitchen' => 'boolean',
            'order_screen' => 'boolean',
            'tables' => 'boolean',
            'invoice_count' => 'integer',
            'interval_count' => 'integer',
            'trial_days' => 'integer',
            'price' => 'decimal:4',
            'created_by' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'mark_package_as_popular' => 'boolean',
            'is_private' => 'boolean',
            'is_one_time' => 'boolean',
            'enable_custom_link' => 'boolean',
            'custom_link' => 'string',
            'custom_link_text' => 'string',
            'deleted_at' => 'timestamp'
        ];
    }
}
