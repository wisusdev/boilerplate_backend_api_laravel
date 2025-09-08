<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationTemplate extends Model
{
    use HasFactory;

    public function variation_value_templates()
    {
        return $this->hasMany(VariationValueTemplate::class, 'variation_template_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer'
        ];
    }
}
