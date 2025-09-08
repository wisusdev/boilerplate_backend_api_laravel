<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResProductModifierSet extends Model
{
    use HasFactory;

    public function product()
    {
        return $this->belongsTo(Product::class, 'modifier_set_id');
    }

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'modifier_set_id' => 'integer',
            'product_id' => 'integer'
        ];
    }
}
