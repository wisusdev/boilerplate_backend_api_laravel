<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'brands';

    protected $fillable = [
        'business_id',
        'created_by',
        'name',
        'description',
        'slug',
        'logo',
        'is_active'
    ];

    protected $dates = [
        'deleted_at',
    ];

    // Relaciones
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeWithProductsCount(Builder $query): Builder
    {
        return $query->withCount('products');
    }

    public function scopeWithActiveProductsCount(Builder $query): Builder
    {
        return $query->withCount(['products as active_products_count' => function ($query) {
            $query->where('is_active', true);
        }]);
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'logo' => 'string',
            'website' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'address' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta_title' => 'string',
            'meta_description' => 'string',
            'meta_keywords' => 'string',
            'created_by' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
