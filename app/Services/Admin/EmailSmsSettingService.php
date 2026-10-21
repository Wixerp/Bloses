<?php

namespace App\Services\Admin;

use App\Models\EmailSmsSetting;
use Illuminate\Support\Facades\DB;

class EmailSmsSettingService
{
    public function getSettings(): array
    {
        $setting = EmailSmsSetting::first();

        if (!$setting) {
            return [
                'id' => null,
                'email_enabled' => true,
                'sms_enabled' => false,
                'mail_driver' => 'smtp',
                'mail_host' => null,
                'mail_port' => 587,
                'mail_username' => null,
                'mail_password_configured' => false,
                'mail_encryption' => 'tls',
                'mail_from_address' => null,
                'mail_from_name' => null,
                'sms_provider' => null,
                'sms_api_url' => null,
                'sms_api_key_configured' => false,
                'sms_sender_id' => null,
                'notification_events' => [],
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [
            'id' => $setting->id,
            'email_enabled' => $setting->email_enabled,
            'sms_enabled' => $setting->sms_enabled,
            'mail_driver' => $setting->mail_driver,
            'mail_host' => $setting->mail_host,
            'mail_port' => $setting->mail_port,
            'mail_username' => $setting->mail_username,
            'mail_password_configured' => filled($setting->mail_password),
            'mail_encryption' => $setting->mail_encryption,
            'mail_from_address' => $setting->mail_from_address,
            'mail_from_name' => $setting->mail_from_name,
            'sms_provider' => $setting->sms_provider,
            'sms_api_url' => $setting->sms_api_url,
            'sms_api_key_configured' => filled($setting->sms_api_key),
            'sms_sender_id' => $setting->sms_sender_id,
            'notification_events' => $setting->notification_events ?? [],
            'created_at' => $setting->created_at,
            'updated_at' => $setting->updated_at,
        ];
    }

    public function save(array $data, ?int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $setting = EmailSmsSetting::first();

            if (!$setting) {
                $setting = new EmailSmsSetting();
            }

            // Blank secret fields preserve existing credentials.
            foreach (['mail_password', 'sms_api_key'] as $secret) {
                if (array_key_exists($secret, $data) &&
                    ($data[$secret] === null || $data[$secret] === '')) {
                    unset($data[$secret]);
                }
            }

            $data['updated_by'] = $userId;

            $setting->fill($data);
            $setting->save();

            return $this->getSettings();
        });
    }
}
