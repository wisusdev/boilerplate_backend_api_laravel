<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthClient extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'secret' => 'string',
            'provider' => 'string',
            'personal_access_client' => 'boolean',
            'password_client' => 'boolean',
            'revoked' => 'boolean'
        ];
    }
}
