<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'slug',
        'sku',
        'price',
        'cost_price',
        'quantity',
        'min_quantity',
        'weight',
        'dimensions',
        'color',
        'size',
        'material',
        'type',
        'description',
        'is_active',
        'sort_order',
        'meta_data',
        'created_by',
        // Campos existentes del sistema
        'sub_sku',
        'product_variation_id',
        'variation_value_id',
        'default_purchase_price',
        'dpp_inc_tax',
        'profit_percent',
        'default_sell_price',
        'sell_price_inc_tax',
    ];

    protected $dates = [
        'deleted_at',
    ];

    // Relaciones nuevas
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relaciones existentes
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function product_variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function variation_location_details()
    {
        return $this->hasMany(VariationLocationDetail::class, 'variation_id');
    }

    public function variation_group_prices()
    {
        return $this->hasMany(VariationGroupPrice::class, 'variation_id');
    }

    public function transaction_sell_lines()
    {
        return $this->hasMany(TransactionSellLine::class, 'variation_id');
    }

    public function stock_adjustment_lines()
    {
        return $this->hasMany(StockAdjustmentLine::class, 'variation_id');
    }

    public function purchase_lines()
    {
        return $this->hasMany(PurchaseLine::class, 'variation_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeByBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('quantity <= min_quantity');
    }

    public function scopeByPriceRange(Builder $query, float $minPrice, float $maxPrice): Builder
    {
        return $query->whereBetween('price', [$minPrice, $maxPrice]);
    }

    // Métodos auxiliares
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }

    public function getStockValue(): float
    {
        return $this->price * $this->quantity;
    }

    public function getCostValue(): float
    {
        return $this->cost_price * $this->quantity;
    }

    public function getProfit(): float
    {
        return ($this->price - $this->cost_price) * $this->quantity;
    }

    public function getProfitMargin(): float
    {
        if ($this->price <= 0) {
            return 0;
        }
        return (($this->price - $this->cost_price) / $this->price) * 100;
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'product_id' => 'integer',
            'name' => 'string',
            'slug' => 'string',
            'sku' => 'string',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'dimensions' => 'string',
            'color' => 'string',
            'size' => 'string',
            'material' => 'string',
            'type' => 'string',
            'description' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta_data' => 'string',
            'created_by' => 'integer',
            'deleted_at' => 'datetime',
            // Campos existentes del sistema
            'sub_sku' => 'string',
            'product_variation_id' => 'integer',
            'variation_value_id' => 'integer',
            'default_purchase_price' => 'decimal:4',
            'dpp_inc_tax' => 'decimal:4',
            'profit_percent' => 'decimal:4',
            'default_sell_price' => 'decimal:4',
            'sell_price_inc_tax' => 'decimal:4',
        ];
    }
}
