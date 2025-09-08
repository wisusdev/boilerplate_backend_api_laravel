<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'location_id',
        'name',
        'landmark',
        'country',
        'state',
        'city',
        'zip_code',
        'invoice_scheme_id',
        'sale_invoice_scheme_id',
        'invoice_layout_id',
        'sale_invoice_layout_id',
        'receipt_printer_type',
        'selling_price_group_id',
        'printer_id',
        'print_receipt_on_invoice',
        'mobile',
        'alternate_number',
        'email',
        'website',
        'default_payment_accounts',
        'featured_products',
        'is_active',
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4'
    ];

    public function getResourceType(): string
    {
        return 'business-locations';
    }

    public function variation_location_details(): HasMany
    {
        return $this->hasMany(VariationLocationDetail::class, 'location_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function invoice_layout(): BelongsTo
    {
        return $this->belongsTo(InvoiceLayout::class, 'invoice_layout_id');
    }

    public function invoice_scheme(): BelongsTo
    {
        return $this->belongsTo(InvoiceScheme::class, 'invoice_scheme_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'string',
            'name' => 'string',
            'country' => 'string',
            'state' => 'string',
            'city' => 'string',
            'invoice_scheme_id' => 'integer',
            'sale_invoice_scheme_id' => 'integer',
            'invoice_layout_id' => 'integer',
            'sale_invoice_layout_id' => 'integer',
            'selling_price_group_id' => 'integer',
            'print_receipt_on_invoice' => 'boolean',
            'printer_id' => 'integer',
            'mobile' => 'string',
            'alternate_number' => 'string',
            'email' => 'string',
            'website' => 'string',
            'is_active' => 'boolean',
            'custom_field1' => 'string',
            'custom_field2' => 'string',
            'custom_field3' => 'string',
            'custom_field4' => 'string',
            'deleted_at' => 'timestamp'
        ];
    }
}
