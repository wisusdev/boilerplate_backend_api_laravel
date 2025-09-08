<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthAccessToken extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'name' => 'string',
            'revoked' => 'boolean',
            'expires_at' => 'datetime'
        ];
    }
}
