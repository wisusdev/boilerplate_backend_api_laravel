<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsDocumentShare extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'value' => 'integer'
        ];
    }
}
