<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'business_id',
        'type',
        'unit_id',
        'sub_unit_ids',
        'category_id',
        'sub_category_id',
        'brand_id',
        'created_by',
        'tax',
        'tax_type',
        'enable_stock',
        'alert_quantity',
        'sku',
        'barcode_type',
        'expiry_period',
        'expiry_period_type',
        'enable_sr_no',
        'weight',
        'product_description',
        'image',
        'applicable_tax',
        'selling_price',
        'selling_price_tax_type',
        'product_custom_field1',
        'product_custom_field2',
        'product_custom_field3',
        'product_custom_field4',
        'product_custom_field5',
        'product_custom_field6',
        'product_custom_field7',
        'product_custom_field8',
        'product_custom_field9',
        'product_custom_field10',
        'product_custom_field11',
        'product_custom_field12',
        'product_custom_field13',
        'product_custom_field14',
        'product_custom_field15',
        'product_custom_field16',
        'product_custom_field17',
        'product_custom_field18',
        'product_custom_field19',
        'product_custom_field20',
        'warranty_id',
        'is_inactive',
        'not_for_selling',
    ];

	public function getResourceType(): string
    {
        return 'products';
    }

    public function variations(): HasMany
    {
        return $this->hasMany(Variation::class, 'product_id');
    }

    public function transaction_sell_lines(): HasMany
    {
        return $this->hasMany(TransactionSellLine::class, 'product_id');
    }

    public function stock_adjustment_lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class, 'product_id');
    }

    public function res_product_modifier_sets(): HasMany
    {
        return $this->hasMany(ResProductModifierSet::class, 'modifier_set_id');
    }

    public function purchase_lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class, 'product_id');
    }

    public function product_variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }

    // Nuevas relaciones agregadas
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function sub_category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tax_rate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'unit_id' => 'integer',
            'secondary_unit_id' => 'integer',
            'brand_id' => 'integer',
            'created_by' => 'integer',
            'category_id' => 'integer',
            'sub_category_id' => 'integer',
            'tax' => 'integer',
            'enable_stock' => 'boolean',
            'alert_quantity' => 'decimal:4',
            'sku' => 'string',
            'expiry_period' => 'decimal:2',
            'enable_sr_no' => 'boolean',
            'weight' => 'string',
            'image' => 'string',
            'product_custom_field1' => 'string',
            'product_custom_field2' => 'string',
            'product_custom_field3' => 'string',
            'product_custom_field4' => 'string',
            'product_custom_field5' => 'string',
            'product_custom_field6' => 'string',
            'product_custom_field7' => 'string',
            'product_custom_field8' => 'string',
            'product_custom_field9' => 'string',
            'product_custom_field10' => 'string',
            'product_custom_field11' => 'string',
            'product_custom_field12' => 'string',
            'product_custom_field13' => 'string',
            'product_custom_field14' => 'string',
            'product_custom_field15' => 'string',
            'product_custom_field16' => 'string',
            'product_custom_field17' => 'string',
            'product_custom_field18' => 'string',
            'product_custom_field19' => 'string',
            'product_custom_field20' => 'string',
            'created_by' => 'integer',
            'preparation_time_in_minutes' => 'integer',
            'warranty_id' => 'integer',
            'is_inactive' => 'boolean',
            'not_for_selling' => 'boolean'
        ];
    }
}
