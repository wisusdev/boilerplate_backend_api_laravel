<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barcode extends Model
{
    use HasFactory;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'width' => 'double',
            'height' => 'double',
            'paper_width' => 'double',
            'paper_height' => 'double',
            'top_margin' => 'double',
            'left_margin' => 'double',
            'row_distance' => 'double',
            'col_distance' => 'double',
            'stickers_in_one_row' => 'integer',
            'is_default' => 'boolean',
            'is_continuous' => 'boolean',
            'stickers_in_one_sheet' => 'integer',
            'business_id' => 'integer'
        ];
    }
}
