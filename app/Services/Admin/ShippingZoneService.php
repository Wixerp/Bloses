<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingZoneService
{
    public function index(Request $request)
    {
        $query = DB::table('shipping_zones as z')
            ->leftJoin('states as st', 'st.id', '=', 'z.state_id')
            ->leftJoin('cities as c', 'c.id', '=', 'z.city_id')
            ->select([
                'z.id',
                'z.name',
                'z.code',
                'z.description',
                'z.state_id',
                'st.name as state_name',
                'z.city_id',
                'c.name as city_name',
                'z.is_nationwide',
                'z.is_active',
                'z.created_at',
                'z.updated_at',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('z.name', 'ILIKE', "%{$search}%")
                    ->orWhere('z.code', 'ILIKE', "%{$search}%")
                    ->orWhere('z.description', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('state_id')) {
            $query->where('z.state_id', $request->state_id);
        }

        if ($request->filled('city_id')) {
            $query->where('z.city_id', $request->city_id);
        }

        if ($request->has('is_nationwide') && $request->is_nationwide !== '') {
            $query->where(
                'z.is_nationwide',
                $request->boolean('is_nationwide')
            );
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where(
                'z.is_active',
                $request->boolean('is_active')
            );
        }

        $perPage = max(
            1,
            min((int) $request->input('per_page', 10), 100)
        );

        return $query->orderByDesc('z.id')->paginate($perPage);
    }

    public function show(int $id): object
    {
        $zone = DB::table('shipping_zones as z')
            ->leftJoin('states as st', 'st.id', '=', 'z.state_id')
            ->leftJoin('cities as c', 'c.id', '=', 'z.city_id')
            ->where('z.id', $id)
            ->select([
                'z.id',
                'z.name',
                'z.code',
                'z.description',
                'z.state_id',
                'st.name as state_name',
                'z.city_id',
                'c.name as city_name',
                'z.is_nationwide',
                'z.is_active',
                'z.created_at',
                'z.updated_at',
            ])
            ->first();

        if (!$zone) {
            abort(404, 'Shipping zone not found.');
        }

        return $zone;
    }

    public function store(array $data): object
    {
        $now = now();

        $id = DB::table('shipping_zones')->insertGetId([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'state_id' => $data['is_nationwide']
                ? null
                : ($data['state_id'] ?? null),
            'city_id' => $data['is_nationwide']
                ? null
                : ($data['city_id'] ?? null),
            'is_nationwide' => $data['is_nationwide'],
            'is_active' => $data['is_active'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id');

        return $this->show((int) $id);
    }

    public function update(int $id, array $data): object
    {
        $current = $this->show($id);

        $isNationwide = $data['is_nationwide']
            ?? $current->is_nationwide;

        $update = [];

        foreach ([
            'name',
            'code',
            'description',
            'state_id',
            'city_id',
            'is_nationwide',
            'is_active',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($isNationwide) {
            $update['state_id'] = null;
            $update['city_id'] = null;
        }

        if ($update !== []) {
            $update['updated_at'] = now();

            DB::table('shipping_zones')
                ->where('id', $id)
                ->update($update);
        }

        return $this->show($id);
    }

    public function updateStatus(int $id, bool $isActive): object
    {
        $this->show($id);

        DB::table('shipping_zones')
            ->where('id', $id)
            ->update([
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        return $this->show($id);
    }

    public function destroy(int $id): void
    {
        $this->show($id);

        $inUse = DB::table('shipping_rates')
            ->where('zone_id', $id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'zone' => [
                    'This zone is being used by shipping rates. Deactivate it instead of deleting it.',
                ],
            ]);
        }

        DB::table('shipping_zones')
            ->where('id', $id)
            ->delete();
    }
}
