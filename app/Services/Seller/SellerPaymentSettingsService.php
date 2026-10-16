<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerPaymentSettingsService
{
    private const SETTING_KEYS = [
        'currency',
        'cod_enabled',
        'online_payment_enabled',
        'cod_minimum_amount',
        'cod_maximum_amount',
    ];

    /**
     * Get seller-visible payment settings and available methods.
     */
    public function index(): array
    {
        $settings = DB::table('payment_settings')
            ->where('is_active', true)
            ->whereIn('key', self::SETTING_KEYS)
            ->orderBy('group')
            ->get([
                'key',
                'value',
                'group',
                'description',
            ]);

        $values = $settings->pluck('value', 'key');

        $codEnabled = $this->toBoolean(
            $values->get('cod_enabled', 'true')
        );

        $methods = $this->getActiveMethods($codEnabled);

        return [
            'settings' => [
                'currency' => $values->get('currency', 'PKR'),
                'cod_enabled' => $codEnabled,
                'online_payment_enabled' => $this->toBoolean(
                    $values->get('online_payment_enabled', 'false')
                ),
                'cod_minimum_amount' => (float) $values->get(
                    'cod_minimum_amount',
                    0
                ),
                'cod_maximum_amount' => (float) $values->get(
                    'cod_maximum_amount',
                    0
                ),
            ],
            'payment_methods' => $methods,
            'payment_methods_count' => $methods->count(),
        ];
    }

    /**
     * Get active payment methods.
     */
    public function methods(): array
    {
        $codSetting = DB::table('payment_settings')
            ->where('key', 'cod_enabled')
            ->where('is_active', true)
            ->value('value');

        // COD is enabled by default if no active setting exists.
        $codEnabled = $codSetting === null
            ? true
            : $this->toBoolean($codSetting);

        $methods = $this->getActiveMethods($codEnabled);

        return [
            'payment_methods' => $methods,
            'total' => $methods->count(),
        ];
    }

    /**
     * Return active methods and respect the global COD setting.
     */
    private function getActiveMethods(bool $codEnabled)
    {
        $query = DB::table('master_types')
            ->where('key', 'payment_method')
            ->where('is_active', true);

        if (!$codEnabled) {
            $query->where('value', '!=', 'cod');
        }

        return $query
            ->orderBy('id')
            ->get([
                'id',
                'value',
                'description',
            ]);
    }

    /**
     * Convert database values into reliable booleans.
     */
    private function toBoolean(mixed $value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
