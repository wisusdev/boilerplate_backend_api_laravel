<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'template_for' => 'string',
            'subject' => 'string',
            'cc' => 'string',
            'bcc' => 'string',
            'auto_send' => 'boolean',
            'auto_send_sms' => 'boolean',
            'auto_send_wa_notif' => 'boolean'
        ];
    }
}
