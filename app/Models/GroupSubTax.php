<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupSubTax extends Model
{
    use HasFactory;
       
    public function tax_rate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_id');
    }

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'group_tax_id' => 'integer',
            'tax_id' => 'integer'
        ];
    }
}
