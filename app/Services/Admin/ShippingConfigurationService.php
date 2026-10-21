<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingConfigurationService
{
    public function index()
    {
        return [
            'methods' => DB::table('shipping_methods as sm')
                ->leftJoin(
                    'master_types as mt',
                    'mt.id',
                    '=',
                    'sm.type_id'
                )
                ->select(
                    'sm.*',
                    'mt.value as type_name'
                )
                ->orderBy('sm.id')
                ->get(),

            'zones' => DB::table('shipping_zones as sz')
                ->leftJoin(
                    'states as s',
                    's.id',
                    '=',
                    'sz.state_id'
                )
                ->leftJoin(
                    'cities as c',
                    'c.id',
                    '=',
                    'sz.city_id'
                )
                ->select(
                    'sz.*',
                    's.name as state_name',
                    'c.name as city_name'
                )
                ->orderBy('sz.id')
                ->get(),

            'rates' => DB::table('shipping_rates as sr')
                ->join(
                    'shipping_methods as sm',
                    'sm.id',
                    '=',
                    'sr.shipping_method_id'
                )
                ->join(
                    'shipping_zones as sz',
                    'sz.id',
                    '=',
                    'sr.shipping_zone_id'
                )
                ->select(
                    'sr.*',
                    'sm.name as shipping_method_name',
                    'sz.name as shipping_zone_name'
                )
                ->orderBy('sr.id')
                ->get(),
        ];
    }

    public function methods()
    {
        return DB::table('shipping_methods as sm')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'sm.type_id')
            ->select('sm.*', 'mt.value as type_name')
            ->orderByDesc('sm.id')
            ->get();
    }

    public function zones()
    {
        return DB::table('shipping_zones as sz')
            ->leftJoin('states as s', 's.id', '=', 'sz.state_id')
            ->leftJoin('cities as c', 'c.id', '=', 'sz.city_id')
            ->select(
                'sz.*',
                's.name as state_name',
                'c.name as city_name'
            )
            ->orderByDesc('sz.id')
            ->get();
    }

    public function rates()
    {
        return DB::table('shipping_rates as sr')
            ->join(
                'shipping_methods as sm',
                'sm.id',
                '=',
                'sr.shipping_method_id'
            )
            ->join(
                'shipping_zones as sz',
                'sz.id',
                '=',
                'sr.shipping_zone_id'
            )
            ->select(
                'sr.*',
                'sm.name as shipping_method_name',
                'sz.name as shipping_zone_name'
            )
            ->orderByDesc('sr.id')
            ->get();
    }

    public function storeMethod(array $data)
    {
        $id = DB::table('shipping_methods')->insertGetId([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'price_per_kg' => $data['price_per_kg'] ?? 0,
            'estimated_min_days' => $data['estimated_min_days'] ?? null,
            'estimated_max_days' => $data['estimated_max_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('shipping_methods')->where('id', $id)->first();
    }

    public function updateMethod(int $id, array $data)
    {
        $this->requireRecord('shipping_methods', $id);

        DB::table('shipping_methods')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return DB::table('shipping_methods')->where('id', $id)->first();
    }

    public function deleteMethod(int $id)
    {
        $this->requireRecord('shipping_methods', $id);

        if (DB::table('shipping_rates')->where('shipping_method_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This method has shipping rates. Remove its rates first.'],
            ]);
        }

        DB::table('shipping_methods')->where('id', $id)->delete();

        return true;
    }

    public function storeZone(array $data)
    {
        $id = DB::table('shipping_zones')->insertGetId([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'is_nationwide' => $data['is_nationwide'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('shipping_zones')->where('id', $id)->first();
    }

    public function updateZone(int $id, array $data)
    {
        $this->requireRecord('shipping_zones', $id);

        DB::table('shipping_zones')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return DB::table('shipping_zones')->where('id', $id)->first();
    }

    public function deleteZone(int $id)
    {
        $this->requireRecord('shipping_zones', $id);

        if (DB::table('shipping_rates')->where('shipping_zone_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This zone has shipping rates. Remove its rates first.'],
            ]);
        }

        DB::table('shipping_zones')->where('id', $id)->delete();

        return true;
    }

    public function storeRate(array $data)
    {
        $id = DB::table('shipping_rates')->insertGetId([
            'shipping_method_id' => $data['shipping_method_id'],
            'shipping_zone_id' => $data['shipping_zone_id'],
            'base_rate' => $data['base_rate'] ?? 0,
            'per_kg_rate' => $data['per_kg_rate'] ?? 0,
            'free_shipping_min_amount' => $data['free_shipping_min_amount'] ?? null,
            'max_weight' => $data['max_weight'] ?? null,
            'currency' => $data['currency'] ?? 'PKR',
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('shipping_rates')->where('id', $id)->first();
    }

    public function updateRate(int $id, array $data)
    {
        $this->requireRecord('shipping_rates', $id);

        DB::table('shipping_rates')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return DB::table('shipping_rates')->where('id', $id)->first();
    }

    public function deleteRate(int $id)
    {
        $this->requireRecord('shipping_rates', $id);

        DB::table('shipping_rates')->where('id', $id)->delete();

        return true;
    }

    private function requireRecord(string $table, int $id): void
    {
        if (!DB::table($table)->where('id', $id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['Record not found.'],
            ]);
        }
    }
}
