<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminSellerService
{
    /**
     * Get all sellers with search, status filter and pagination.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('sellers as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as st', 'st.id', '=', 's.status_id')
            ->leftJoinSub(
                DB::table('seller_store_settings')
                    ->select('seller_id')
                    ->selectRaw('COUNT(*) as total_stores')
                    ->selectRaw(
                        'COUNT(*) FILTER (WHERE is_verified = true) as verified_stores'
                    )
                    ->groupBy('seller_id'),
                'store_stats',
                'store_stats.seller_id',
                '=',
                's.id'
            )
            ->leftJoinSub(
                DB::table('seller_store_settings as ss')
                    ->join('products as p', 'p.store_id', '=', 'ss.id')
                    ->select('ss.seller_id')
                    ->selectRaw('COUNT(p.id) as total_products')
                    ->groupBy('ss.seller_id'),
                'product_stats',
                'product_stats.seller_id',
                '=',
                's.id'
            )
            ->select([
                's.id as seller_id',
                's.user_id',
                'u.name',
                'u.email',
                'u.phone',
                's.status_id',
                'st.description as status',
            ])
            ->selectRaw(
                'COALESCE(store_stats.total_stores, 0) as total_stores'
            )
            ->selectRaw(
                'COALESCE(store_stats.verified_stores, 0) as verified_stores'
            )
            ->selectRaw(
                'COALESCE(product_stats.total_products, 0) as total_products'
            );

        // Search by seller name, email or phone.
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'ILIKE', "%{$search}%")
                    ->orWhere('u.email', 'ILIKE', "%{$search}%")
                    ->orWhere('u.phone', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by seller status ID.
        if (!empty($filters['status_id'])) {
            $query->where('s.status_id', $filters['status_id']);
        }

        // Filter sellers by store verification.
        if (isset($filters['is_verified']) &&
            $filters['is_verified'] !== '') {

            $verified = filter_var(
                $filters['is_verified'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($verified !== null) {
                $query->whereExists(function ($subquery) use ($verified) {
                    $subquery->selectRaw('1')
                        ->from('seller_store_settings as ss_filter')
                        ->whereColumn('ss_filter.seller_id', 's.id')
                        ->where('ss_filter.is_verified', $verified);
                });
            }
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 10), 1),
            100
        );

        return $query
            ->orderByDesc('s.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get one seller with store and product details.
     */
    public function getById(int $sellerId): ?object
    {
        $seller = DB::table('sellers as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as st', 'st.id', '=', 's.status_id')
            ->where('s.id', $sellerId)
            ->select([
                's.id as seller_id',
                's.user_id',
                'u.name',
                'u.email',
                'u.phone',
                's.status_id',
                'st.description as status',
            ])
            ->first();

        if (!$seller) {
            return null;
        }

        $seller->stores = DB::table('seller_store_settings as ss')
            ->where('ss.seller_id', $sellerId)
            ->select([
                'ss.id as store_id',
                'ss.seller_id',
                'ss.is_verified',
            ])
            ->selectSub(
                DB::table('products as p')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('p.store_id', 'ss.id'),
                'total_products'
            )
            ->orderBy('ss.id')
            ->get();

        return $seller;
    }
}
