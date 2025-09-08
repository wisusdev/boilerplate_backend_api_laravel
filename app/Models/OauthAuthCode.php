<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthAuthCode extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'revoked' => 'boolean',
            'expires_at' => 'datetime'
        ];
    }
}
