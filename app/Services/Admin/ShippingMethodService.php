<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingMethodService
{
    public function index(Request $request)
    {
        $query = DB::table('shipping_methods as sm')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'sm.type_id')
            ->select([
                'sm.id',
                'sm.name',
                'sm.code',
                'sm.description',
                'sm.type_id',
                'mt.description as type_name',
                'sm.base_price',
                'sm.price_per_kg',
                'sm.estimated_min_days',
                'sm.estimated_max_days',
                'sm.is_active',
                'sm.created_at',
                'sm.updated_at',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('sm.name', 'ILIKE', "%{$search}%")
                    ->orWhere('sm.code', 'ILIKE', "%{$search}%")
                    ->orWhere('sm.description', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('sm.type_id', $request->type_id);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('sm.is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return $query->orderByDesc('sm.id')->paginate($perPage);
    }

    public function show(int $id): object
    {
        $method = DB::table('shipping_methods as sm')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'sm.type_id')
            ->where('sm.id', $id)
            ->select([
                'sm.*',
                'mt.description as type_name',
            ])
            ->first();

        if (!$method) {
            abort(404, 'Shipping method not found.');
        }

        return $method;
    }

    public function store(array $data): object
    {
        $now = now();

        $id = DB::table('shipping_methods')->insertGetId([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type_id' => $data['type_id'],
            'base_price' => $data['base_price'],
            'price_per_kg' => $data['price_per_kg'] ?? 0,
            'estimated_min_days' => $data['estimated_min_days'],
            'estimated_max_days' => $data['estimated_max_days'],
            'is_active' => $data['is_active'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id');

        return $this->show((int) $id);
    }

    public function update(int $id, array $data): object
    {
        $this->show($id);

        $data['updated_at'] = now();

        DB::table('shipping_methods')
            ->where('id', $id)
            ->update($data);

        return $this->show($id);
    }

    public function updateStatus(int $id, bool $isActive): object
    {
        $this->show($id);

        DB::table('shipping_methods')
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

        $inUse = DB::table('shipments')
            ->where('shipping_method_id', $id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'shipping_method' => [
                    'This shipping method is already used by shipments. Deactivate it instead of deleting it.',
                ],
            ]);
        }

        DB::table('shipping_methods')
            ->where('id', $id)
            ->delete();
    }
}
