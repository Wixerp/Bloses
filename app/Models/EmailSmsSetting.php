<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSmsSetting extends Model
{
    protected $fillable = [
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'email_enabled',
        'sms_provider',
        'sms_api_url',
        'sms_api_key',
        'sms_sender_id',
        'sms_enabled',
        'notification_events',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'mail_password' => 'encrypted',
            'sms_api_key' => 'encrypted',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'mail_port' => 'integer',
            'notification_events' => 'array',
        ];
    }
}
