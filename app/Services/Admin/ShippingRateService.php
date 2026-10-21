<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingRateService
{
    public function getAll(Request $request): array
    {
        $filters = $request->validate([
            'shipping_method_id' => 'nullable|integer|min:1',
            'zone_id'             => 'nullable|integer|min:1',
            'is_active'           => 'nullable|boolean',
            'per_page'            => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('shipping_rates as sr')
            ->leftJoin(
                'shipping_methods as sm',
                'sm.id',
                '=',
                'sr.shipping_method_id'
            )
            ->leftJoin(
                'shipping_zones as sz',
                'sz.id',
                '=',
                'sr.shipping_zone_id'
            )
            ->select([
                'sr.id',
                'sr.shipping_method_id',
                'sm.name as shipping_method_name',
                'sr.shipping_zone_id',
                'sz.*',
                'sr.base_rate',
                'sr.per_kg_rate',
                'sr.free_shipping_min_amount',
                'sr.max_weight',
                'sr.currency',
                'sr.is_active',
                'sr.created_at',
                'sr.updated_at',
            ]);

        if (!empty($filters['shipping_method_id'])) {
            $query->where(
                'sr.shipping_method_id',
                $filters['shipping_method_id']
            );
        }

        if (!empty($filters['zone_id'])) {
            $query->where(
                'sr.shipping_zone_id',
                $filters['zone_id']
            );
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('sr.is_active', $filters['is_active']);
        }

        $rates = $query
            ->orderByDesc('sr.id')
            ->paginate($filters['per_page'] ?? 10);

        return ['rates' => $rates];
    }
}
