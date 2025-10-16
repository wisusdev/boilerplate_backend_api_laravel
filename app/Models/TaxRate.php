<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'created_by',
        'name',
        'amount',
        'is_tax_group',
        'for_tax_group',
    ];

	public function getResourceType(): string
    {
        return 'tax-rates';
    }

    public function transaction_sell_lines()
    {
        return $this->hasMany(TransactionSellLine::class, 'tax_id');
    }

    public function purchase_lines()
    {
        return $this->hasMany(PurchaseLine::class, 'tax_id');
    }

    public function group_sub_taxes()
    {
        return $this->hasMany(GroupSubTax::class, 'group_tax_id');
    }

    public function businesses()
    {
        return $this->hasMany(Business::class, 'default_sales_tax');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'amount' => 'double',
            'is_tax_group' => 'boolean',
            'for_tax_group' => 'boolean',
            'created_by' => 'integer',
            'deleted_at' => 'timestamp'
        ];
    }
}
