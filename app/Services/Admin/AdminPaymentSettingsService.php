<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPaymentSettingsService
{
    private const ALLOWED_SETTINGS = [
        'currency',
        'cod_enabled',
        'online_payment_enabled',
        'cod_minimum_amount',
        'cod_maximum_amount',
    ];

    /**
     * Get all payment settings.
     */
    public function index(): array
    {
        $settings = DB::table('payment_settings')
            ->orderBy('group')
            ->orderBy('key')
            ->get([
                'id',
                'key',
                'value',
                'group',
                'description',
                'is_active',
                'updated_by',
                'created_at',
                'updated_at',
            ]);

        return [
            'settings' => $settings,
            'total' => $settings->count(),
        ];
    }

    /**
     * Update multiple settings.
     *
     * Expected format:
     * [
     *   'cod_enabled' => true,
     *   'currency' => 'PKR'
     * ]
     */
    public function update(array $settings): array
    {
        return DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                $this->validateKey($key);

                DB::table('payment_settings')
                    ->where('key', $key)
                    ->update([
                        'value' => $this->normalizeValue($value),
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
            }

            return $this->index();
        });
    }

    /**
     * Update one setting.
     */
    public function updateOne(string $key, mixed $value): array
    {
        $this->validateKey($key);

        $setting = DB::table('payment_settings')
            ->where('key', $key)
            ->first();

        if (!$setting) {
            throw ValidationException::withMessages([
                'key' => ["Payment setting '{$key}' was not found."],
            ]);
        }

        DB::table('payment_settings')
            ->where('key', $key)
            ->update([
                'value' => $this->normalizeValue($value),
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

        return [
            'setting' => DB::table('payment_settings')
                ->where('key', $key)
                ->first(),
        ];
    }

    /**
     * List payment methods from master_types.
     */
    public function methods(): array
    {
        $methods = DB::table('master_types')
            ->where('key', 'payment_method')
            ->orderBy('id')
            ->get([
                'id',
                'key',
                'value',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return [
            'methods' => $methods,
            'total' => $methods->count(),
        ];
    }

    /**
     * Enable or disable a payment method.
     */
    public function updateMethod(int $id, bool $isActive): array
    {
        $method = DB::table('master_types')
            ->where('id', $id)
            ->where('key', 'payment_method')
            ->first();

        if (!$method) {
            throw ValidationException::withMessages([
                'id' => ['Payment method not found.'],
            ]);
        }

        DB::table('master_types')
            ->where('id', $id)
            ->where('key', 'payment_method')
            ->update([
                'is_active' => $isActive,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

        return [
            'method' => DB::table('master_types')
                ->where('id', $id)
                ->first([
                    'id',
                    'key',
                    'value',
                    'description',
                    'is_active',
                    'updated_by',
                    'updated_at',
                ]),
        ];
    }

    private function validateKey(string $key): void
    {
        if (!in_array($key, self::ALLOWED_SETTINGS, true)) {
            throw ValidationException::withMessages([
                'key' => ['This payment setting cannot be updated.'],
            ]);
        }
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            throw ValidationException::withMessages([
                'value' => ['Setting value must be a string, number, or boolean.'],
            ]);
        }

        return (string) $value;
    }
}
