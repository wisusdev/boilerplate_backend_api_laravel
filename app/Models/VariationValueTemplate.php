<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationValueTemplate extends Model
{
    use HasFactory;

    public function variation_template()
    {
        return $this->belongsTo(VariationTemplate::class, 'variation_template_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'variation_template_id' => 'integer'
        ];
    }
}
