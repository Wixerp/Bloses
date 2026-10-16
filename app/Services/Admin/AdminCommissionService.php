<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminCommissionService
{
    public function index(array $filters = []): array
    {
        $query = DB::table('commission_settings as cs')
            ->leftJoin('sellers as s', 's.id', '=', 'cs.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->select(
                'cs.id',
                'cs.seller_id',
                'cs.name',
                'cs.commission_type',
                'cs.commission_rate',
                'cs.is_active',
                'cs.created_at',
                'cs.updated_at',
                'u.name as seller_name',
                'u.email as seller_email'
            );

        if (isset($filters['seller_id'])) {
            $query->where('cs.seller_id', $filters['seller_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('cs.is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('cs.name', 'ilike', "%{$search}%")
                    ->orWhere('u.name', 'ilike', "%{$search}%")
                    ->orWhere('u.email', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByDesc('cs.id')
            ->paginate($filters['per_page'] ?? 15);

        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(array $data, int $adminId): object
    {
        return DB::transaction(function () use ($data, $adminId) {
            $sellerId = $data['seller_id'] ?? null;

            if ($sellerId !== null) {
                $exists = DB::table('commission_settings')
                    ->where('seller_id', $sellerId)
                    ->where('is_active', true)
                    ->exists();

                if ($exists && ($data['is_active'] ?? true)) {
                    throw ValidationException::withMessages([
                        'seller_id' => [
                            'Is seller ki active commission setting pehle se mojood hai.',
                        ],
                    ]);
                }
            } elseif (($data['is_active'] ?? true) &&
                DB::table('commission_settings')
                    ->whereNull('seller_id')
                    ->where('is_active', true)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'seller_id' => [
                        'Active default commission pehle se mojood hai. Existing setting update karein.',
                    ],
                ]);
            }

            $id = DB::table('commission_settings')->insertGetId([
                'seller_id' => $sellerId,
                'name' => $data['name'],
                'commission_type' => $data['commission_type'],
                'commission_rate' => $data['commission_rate'],
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('commission_settings')
                ->where('id', $id)
                ->first();
        });
    }

    public function show(int $id): object
    {
        $setting = DB::table('commission_settings as cs')
            ->leftJoin('sellers as s', 's.id', '=', 'cs.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('cs.id', $id)
            ->select(
                'cs.*',
                'u.name as seller_name',
                'u.email as seller_email'
            )
            ->first();

        if (!$setting) {
            abort(404, 'Commission setting nahi mili.');
        }

        return $setting;
    }

    public function update(int $id, array $data): object
    {
        return DB::transaction(function () use ($id, $data) {
            $current = DB::table('commission_settings')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$current) {
                abort(404, 'Commission setting nahi mili.');
            }

            $sellerId = array_key_exists('seller_id', $data)
                ? $data['seller_id']
                : $current->seller_id;

            $isActive = $data['is_active'] ?? $current->is_active;

            if ($isActive) {
                $duplicate = DB::table('commission_settings')
                    ->where('id', '!=', $id)
                    ->where('is_active', true)
                    ->when(
                        $sellerId === null,
                        fn ($q) => $q->whereNull('seller_id'),
                        fn ($q) => $q->where('seller_id', $sellerId)
                    )
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'seller_id' => [
                            'Is scope ke liye active commission setting pehle se hai.',
                        ],
                    ]);
                }
            }

            DB::table('commission_settings')
                ->where('id', $id)
                ->update([
                    'seller_id' => $sellerId,
                    'name' => $data['name'] ?? $current->name,
                    'commission_type' =>
                        $data['commission_type'] ?? $current->commission_type,
                    'commission_rate' =>
                        $data['commission_rate'] ?? $current->commission_rate,
                    'is_active' => $isActive,
                    'updated_at' => now(),
                ]);

            return $this->show($id);
        });
    }

    public function updateStatus(int $id, bool $isActive): object
    {
        return $this->update($id, ['is_active' => $isActive]);
    }

    public function history(array $filters = []): array
    {
        $query = DB::table('commission_records as cr')
            ->join('orders as o', 'o.id', '=', 'cr.order_id')
            ->join('sellers as s', 's.id', '=', 'cr.seller_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select(
                'cr.id',
                'cr.order_id',
                'o.order_number',
                'cr.seller_id',
                'u.name as seller_name',
                'cr.sale_amount',
                'cr.commission_type',
                'cr.commission_rate',
                'cr.commission_amount',
                'cr.seller_amount',
                'cr.status',
                'cr.created_at'
            );

        if (isset($filters['seller_id'])) {
            $query->where('cr.seller_id', $filters['seller_id']);
        }

        if (isset($filters['status'])) {
            $query->where('cr.status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('cr.created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('cr.created_at', '<=', $filters['to']);
        }

        $paginator = $query
            ->orderByDesc('cr.id')
            ->paginate($filters['per_page'] ?? 15);

        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
