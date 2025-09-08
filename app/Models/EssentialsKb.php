<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsKb extends Model
{
    use HasFactory;

    public function essentials_kb()
    {
        return $this->belongsTo(EssentialsKb::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'title' => 'string',
            'status' => 'string',
            'kb_type' => 'string',
            'share_with' => 'string'
        ];
    }
}
