<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\EmailSmsSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailSmsSettingController extends Controller
{
    public function __construct(
        private EmailSmsSettingService $service
    ) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Email and SMS settings fetched successfully.',
            'data' => $this->service->getSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],

            'mail_driver' => ['sometimes', Rule::in(['smtp'])],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['sometimes', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2000'],
            'mail_encryption' => [
                'nullable',
                Rule::in(['tls', 'ssl', '']),
            ],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],

            'sms_provider' => ['nullable', 'string', 'max:100'],
            'sms_api_url' => ['nullable', 'url', 'max:2000'],
            'sms_api_key' => ['nullable', 'string', 'max:4000'],
            'sms_sender_id' => ['nullable', 'string', 'max:100'],

            'notification_events' => ['sometimes', 'array'],
            'notification_events.registration' => ['sometimes', 'array'],
            'notification_events.registration.email' => ['sometimes', 'boolean'],
            'notification_events.registration.sms' => ['sometimes', 'boolean'],
            'notification_events.order_confirmation' => ['sometimes', 'array'],
            'notification_events.order_confirmation.email' => ['sometimes', 'boolean'],
            'notification_events.order_confirmation.sms' => ['sometimes', 'boolean'],
            'notification_events.order_status' => ['sometimes', 'array'],
            'notification_events.order_status.email' => ['sometimes', 'boolean'],
            'notification_events.order_status.sms' => ['sometimes', 'boolean'],
            'notification_events.password_reset' => ['sometimes', 'array'],
            'notification_events.password_reset.email' => ['sometimes', 'boolean'],
            'notification_events.password_reset.sms' => ['sometimes', 'boolean'],
            'notification_events.seller_notification' => ['sometimes', 'array'],
            'notification_events.seller_notification.email' => ['sometimes', 'boolean'],
            'notification_events.seller_notification.sms' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->user()?->id;

        $settings = $this->service->save($data, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Email and SMS settings updated successfully.',
            'data' => $settings,
        ]);
    }

    public function testEmail(Request $request)
    {
        $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        $settings = $this->service->getSettings();

        if (!$settings['email_enabled']) {
            throw ValidationException::withMessages([
                'email_enabled' => 'Email notifications are disabled.',
            ]);
        }

        $record = \App\Models\EmailSmsSetting::first();

        if (
            !$record ||
            !$record->mail_host ||
            !$record->mail_from_address ||
            !$record->mail_username ||
            !$record->mail_password
        ) {
            throw ValidationException::withMessages([
                'mail' => 'Complete SMTP settings before testing email.',
            ]);
        }

        try {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $record->mail_host,
                'mail.mailers.smtp.port' => $record->mail_port,
                'mail.mailers.smtp.username' => $record->mail_username,
                'mail.mailers.smtp.password' => $record->mail_password,
                'mail.mailers.smtp.encryption' => $record->mail_encryption ?: null,
                'mail.from.address' => $record->mail_from_address,
                'mail.from.name' => $record->mail_from_name ?: config('app.name'),
            ]);

            Mail::mailer('smtp')->raw(
                'This is a test email from your Blose Admin Panel.',
                function ($message) use ($request, $record) {
                    $message->to($request->input('to'))
                        ->subject('Blose SMTP Test Email');
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully.',
                'data' => ['to' => $request->input('to')],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Test email failed. Check SMTP settings and Laravel logs.',
            ], 422);
        }
    }

    public function testSms(Request $request)
    {
        $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $record = \App\Models\EmailSmsSetting::first();

        if (!$record || !$record->sms_enabled) {
            throw ValidationException::withMessages([
                'sms_enabled' => 'SMS is disabled or not configured.',
            ]);
        }

        if (!$record->sms_api_url || !$record->sms_api_key) {
            throw ValidationException::withMessages([
                'sms' => 'Configure SMS API URL and API key first.',
            ]);
        }

        try {
            // Generic JSON API format. Adjust the payload and authentication
            // headers to match your selected SMS provider's documentation.
            $response = Http::timeout(20)
                ->withToken($record->sms_api_key)
                ->acceptJson()
                ->post($record->sms_api_url, [
                    'to' => $request->input('to'),
                    'message' => $request->input('message'),
                    'sender' => $record->sms_sender_id,
                ]);

            if (!$response->successful()) {
                report(new \RuntimeException(
                    'SMS provider returned HTTP '.$response->status()
                ));

                return response()->json([
                    'success' => false,
                    'message' => 'SMS provider rejected the request. Check provider configuration and logs.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'SMS request accepted by the provider.',
                'data' => ['to' => $request->input('to')],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'SMS request failed. Check provider configuration and Laravel logs.',
            ], 422);
        }
    }
}
