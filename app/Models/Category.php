<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'business_id',
        'created_by',
        'short_code',
        'parent_id',
        'category_type',
        'description',
        'slug',
        'is_active',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope para categorías principales (sin padre)
    public function scopeMain($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope para subcategorías
    public function scopeSubcategories($query)
    {
        return $query->whereNotNull('parent_id');
    }

    // Scope por tipo de categoría
    public function scopeByType($query, string $type)
    {
        return $query->where('category_type', $type);
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'short_code' => 'string',
            'parent_id' => 'integer',
            'created_by' => 'integer',
            'category_type' => 'string',
            'description' => 'string',
            'slug' => 'string',
            'is_active' => 'boolean',
            'deleted_at' => 'timestamp'
        ];
    }
}
