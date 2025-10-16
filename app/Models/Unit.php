<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'created_by',
        'is_active',
        'name',
        'short_name',
        'allow_decimal',
        'base_unit_id',
        'base_unit_multiplier',
    ];

	public function getResourceType(): string
    {
        return 'units';
    }

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
        return $this->hasMany(Product::class, 'unit_id');
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

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBaseUnits(Builder $query): Builder
    {
        return $query->whereNull('base_unit');
    }

    public function scopeDerivedUnits(Builder $query): Builder
    {
        return $query->whereNotNull('base_unit');
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

    // Métodos auxiliares
    public function isBaseUnit(): bool
    {
        return $this->base_unit === null;
    }

    public function canConvertTo(Unit $targetUnit): bool
    {
        // Solo se pueden convertir unidades del mismo tipo
        return $this->type === $targetUnit->type && $this->business_id === $targetUnit->business_id;
    }

    public function convertValue(float $value, Unit $targetUnit): float
    {
        if (!$this->canConvertTo($targetUnit)) {
            throw new \InvalidArgumentException('No se puede convertir entre estas unidades.');
        }

        // Si ambas son unidades base o la misma unidad, no hay conversión
        if ($this->id === $targetUnit->id) {
            return $value;
        }

        // Convertir a unidad base primero
        $baseValue = $this->isBaseUnit() ? $value : $this->convertToBase($value);

        // Convertir de unidad base a unidad objetivo
        return $targetUnit->isBaseUnit() ? $baseValue : $targetUnit->convertFromBase($baseValue);
    }

    private function convertToBase(float $value): float
    {
        return match ($this->operator) {
            '*' => $value * $this->operation_value,
            '/' => $value / $this->operation_value,
            '+' => $value + $this->operation_value,
            '-' => $value - $this->operation_value,
            default => $value,
        };
    }

    private function convertFromBase(float $baseValue): float
    {
        return match ($this->operator) {
            '*' => $baseValue / $this->operation_value,
            '/' => $baseValue * $this->operation_value,
            '+' => $baseValue - $this->operation_value,
            '-' => $baseValue + $this->operation_value,
            default => $baseValue,
        };
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'created_by' => 'integer',
            'is_active' => 'boolean',
            'name' => 'string',
            'short_name' => 'string',
            'allow_decimal' => 'boolean',
            'base_unit_id' => 'integer',
            'base_unit_multiplier' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
