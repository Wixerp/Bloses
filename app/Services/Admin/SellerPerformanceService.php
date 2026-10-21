<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerPerformanceService
{
    public function getAll(array $filters = [])
    {
        $hasOrderSeller = Schema::hasColumn('orders', 'seller_id');

        // Use the first available sales amount column.
        $amountColumn = null;

        foreach (['total_amount', 'grand_total', 'total', 'subtotal'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                $amountColumn = $column;
                break;
            }
        }

        $storeStats = DB::table('seller_store_settings')
            ->select('seller_id')
            ->selectRaw('COUNT(*) AS total_stores')
            ->selectRaw(
                'COUNT(*) FILTER (WHERE is_verified = true) AS verified_stores'
            )
            ->groupBy('seller_id');

        $productStats = DB::table('seller_store_settings as ss')
            ->join('products as p', 'p.store_id', '=', 'ss.id')
            ->select('ss.seller_id')
            ->selectRaw('COUNT(p.id) AS total_products')
            ->groupBy('ss.seller_id');

        $query = DB::table('sellers as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 's.status_id')
            ->leftJoinSub($storeStats, 'stores', function ($join) {
                $join->on('stores.seller_id', '=', 's.id');
            })
            ->leftJoinSub($productStats, 'products', function ($join) {
                $join->on('products.seller_id', '=', 's.id');
            })
            ->select([
                's.id as seller_id',
                's.user_id',
                'u.name as seller_name',
                'u.email',
                's.status_id',
                'mt.description as seller_status',
            ])
            ->selectRaw('COALESCE(stores.total_stores, 0) AS total_stores')
            ->selectRaw('COALESCE(stores.verified_stores, 0) AS verified_stores')
            ->selectRaw('COALESCE(products.total_products, 0) AS total_products');

        if ($hasOrderSeller) {
            $orderStats = DB::table('orders')
                ->select('seller_id')
                ->selectRaw('COUNT(*) AS total_orders');

            if ($amountColumn) {
                $orderStats->selectRaw(
                    'COALESCE(SUM(' . $amountColumn . '), 0) AS total_sales'
                );
            } else {
                $orderStats->selectRaw('0 AS total_sales');
            }

            if (Schema::hasColumn('orders', 'created_at')) {
                if (!empty($filters['from_date'])) {
                    $orderStats->whereDate(
                        'created_at',
                        '>=',
                        $filters['from_date']
                    );
                }

                if (!empty($filters['to_date'])) {
                    $orderStats->whereDate(
                        'created_at',
                        '<=',
                        $filters['to_date']
                    );
                }
            }

            $orderStats->groupBy('seller_id');

            $query->leftJoinSub($orderStats, 'orders_stats', function ($join) {
                $join->on('orders_stats.seller_id', '=', 's.id');
            })
            ->addSelect(DB::raw(
                'COALESCE(orders_stats.total_orders, 0) AS total_orders'
            ))
            ->addSelect(DB::raw(
                'COALESCE(orders_stats.total_sales, 0) AS total_sales'
            ));
        } else {
            $query->addSelect(DB::raw('0 AS total_orders'));
            $query->addSelect(DB::raw('0 AS total_sales'));
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'ILIKE', "%{$search}%")
                    ->orWhere('u.email', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($filters['status_id'])) {
            $query->where('s.status_id', $filters['status_id']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);

        return $query->orderByDesc('s.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getById(int $sellerId, array $filters = []): ?object
    {
        $result = $this->getAll([
            ...$filters,
            'per_page' => 1,
            'search' => '',
        ]);

        // Query the specific seller using the same performance calculation.
        $hasOrderSeller = Schema::hasColumn('orders', 'seller_id');

        $seller = DB::table('sellers as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $sellerId)
            ->select('s.id as seller_id', 's.user_id', 'u.name as seller_name', 'u.email')
            ->first();

        if (!$seller) {
            return null;
        }

        $seller->stores = DB::table('seller_store_settings as ss')
            ->where('ss.seller_id', $sellerId)
            ->select('ss.id as store_id', 'ss.is_verified')
            ->selectSub(
                DB::table('products as p')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('p.store_id', 'ss.id'),
                'total_products'
            )
            ->get();

        $seller->total_products = DB::table('seller_store_settings as ss')
            ->join('products as p', 'p.store_id', '=', 'ss.id')
            ->where('ss.seller_id', $sellerId)
            ->count();

        $seller->total_orders = 0;
        $seller->total_sales = 0;

        if ($hasOrderSeller) {
            $orders = DB::table('orders')->where('seller_id', $sellerId);

            if (Schema::hasColumn('orders', 'created_at')) {
                if (!empty($filters['from_date'])) {
                    $orders->whereDate('created_at', '>=', $filters['from_date']);
                }

                if (!empty($filters['to_date'])) {
                    $orders->whereDate('created_at', '<=', $filters['to_date']);
                }
            }

            $seller->total_orders = (clone $orders)->count();

            foreach (['total_amount', 'grand_total', 'total', 'subtotal'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $seller->total_sales = (clone $orders)->sum($column);
                    break;
                }
            }
        }

        return $seller;
    }
}
