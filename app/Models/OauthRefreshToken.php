<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthRefreshToken extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'access_token_id' => 'string',
            'revoked' => 'boolean',
            'expires_at' => 'datetime'
        ];
    }
}
