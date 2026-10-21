<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminInventoryService
{
    public function index(array $filters = []): array
    {
        $query = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin(
                'seller_store_settings as sss',
                'sss.id',
                '=',
                'p.store_id'
            )
            ->select(
                'pv.id as variant_id',
                'pv.product_id',
                'p.name as product_name',
                'p.product_code',
                'sss.store_name',
                'pv.name as variant_name',
                'pv.sku',
                'pv.barcode',
                'pv.price',
                'pv.manage_stock',
                'pv.is_active',
                'pv.is_default'
            )
            ->selectRaw(
                "COALESCE(NULLIF(pv.stock_qty, '')::integer, 0) AS stock_qty"
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'ILIKE', "%{$search}%")
                    ->orWhere('p.product_code', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.name', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.sku', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.barcode', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['product_id'])) {
            $query->where('pv.product_id', $filters['product_id']);
        }

        if (isset($filters['manage_stock'])) {
            $query->where(
                'pv.manage_stock',
                filter_var($filters['manage_stock'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        if (isset($filters['is_active'])) {
            $query->where(
                'pv.is_active',
                filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        if (($filters['stock_status'] ?? null) === 'out_of_stock') {
            $query->whereRaw(
                "COALESCE(NULLIF(pv.stock_qty, '')::integer, 0) <= 0"
            );
        } elseif (($filters['stock_status'] ?? null) === 'low_stock') {
            $threshold = (int) ($filters['threshold'] ?? 5);

            $query->whereRaw(
                "COALESCE(NULLIF(pv.stock_qty, '')::integer, 0) > 0"
            )->whereRaw(
                "COALESCE(NULLIF(pv.stock_qty, '')::integer, 0) <= ?",
                [$threshold]
            );
        } elseif (($filters['stock_status'] ?? null) === 'in_stock') {
            $query->whereRaw(
                "COALESCE(NULLIF(pv.stock_qty, '')::integer, 0) > ?",
                [(int) ($filters['threshold'] ?? 5)]
            );
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $paginator = $query
            ->orderBy('p.name')
            ->orderBy('pv.id')
            ->paginate($perPage);

        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function summary(int $threshold = 5): array
    {
        $rows = DB::table('product_variants')
            ->selectRaw(
                "COALESCE(NULLIF(stock_qty, '')::integer, 0) AS qty"
            )
            ->where('manage_stock', true)
            ->get();

        $quantities = $rows->pluck('qty')
            ->map(fn ($qty) => (int) $qty);

        return [
            'total_variants' => $rows->count(),
            'in_stock' => $quantities->filter(
                fn ($qty) => $qty > $threshold
            )->count(),
            'low_stock' => $quantities->filter(
                fn ($qty) => $qty > 0 && $qty <= $threshold
            )->count(),
            'out_of_stock' => $quantities->filter(
                fn ($qty) => $qty <= 0
            )->count(),
            'threshold' => $threshold,
        ];
    }

    public function adjustStock(
        int $variantId,
        array $data,
        ?int $adminId
    ): array {
        return DB::transaction(function () use (
            $variantId,
            $data,
            $adminId
        ) {
            $variant = DB::table('product_variants')
                ->where('id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['Product variant not found.'],
                ]);
            }

            if (!$variant->manage_stock) {
                throw ValidationException::withMessages([
                    'variant_id' => [
                        'Stock management is disabled for this variant.',
                    ],
                ]);
            }

            $previousQty = (int) ($variant->stock_qty ?: 0);

            $mode = $data['mode'];
            $quantity = (int) $data['quantity'];

            $newQty = match ($mode) {
                'set' => $quantity,
                'increase' => $previousQty + $quantity,
                'decrease' => $previousQty - $quantity,
            };

            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'Stock quantity cannot be negative.',
                    ],
                ]);
            }

            $changeQty = $newQty - $previousQty;

            DB::table('product_variants')
                ->where('id', $variantId)
                ->update([
                    'stock_qty' => (string) $newQty,
                    'updated_at' => now(),
                ]);

            DB::table('inventory_stock_movements')->insert([
                'product_variant_id' => $variantId,
                'previous_qty' => $previousQty,
                'change_qty' => $changeQty,
                'new_qty' => $newQty,
                'reason' => $data['reason'],
                'adjusted_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'variant_id' => $variantId,
                'previous_qty' => $previousQty,
                'change_qty' => $changeQty,
                'new_qty' => $newQty,
                'reason' => $data['reason'],
            ];
        });
    }

    public function history(int $variantId, int $perPage = 20): array
    {
        $exists = DB::table('product_variants')
            ->where('id', $variantId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'variant_id' => ['Product variant not found.'],
            ]);
        }

        $paginator = DB::table('inventory_stock_movements as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.adjusted_by')
            ->where('m.product_variant_id', $variantId)
            ->select(
                'm.id',
                'm.product_variant_id',
                'm.previous_qty',
                'm.change_qty',
                'm.new_qty',
                'm.reason',
                'm.adjusted_by',
                'u.name as admin_name',
                'm.created_at'
            )
            ->orderByDesc('m.id')
            ->paginate(min(max($perPage, 1), 100));

        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
