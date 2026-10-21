<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SellerReportService
{
    private function salesQuery(Request $request)
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereRaw(
                    'COALESCE(o.placed_at, o.created_at) >= ?',
                    [$request->date_from . ' 00:00:00']
                );
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereRaw(
                    'COALESCE(o.placed_at, o.created_at) < ?',
                    [
                        date(
                            'Y-m-d',
                            strtotime($request->date_to . ' +1 day')
                        ) . ' 00:00:00'
                    ]
                );
            })
            ->selectRaw('
                oi.seller_id,
                COUNT(DISTINCT o.id) AS total_orders,
                COUNT(DISTINCT CASE
                    WHEN o.status_id = 48 THEN o.id
                END) AS delivered_orders,
                COUNT(DISTINCT CASE
                    WHEN o.status_id = 49 THEN o.id
                END) AS cancelled_orders,
                COALESCE(SUM(oi.quantity), 0) AS items_sold,
                COUNT(DISTINCT oi.product_id) AS products_sold,
                COALESCE(SUM(
                    CASE WHEN o.status_id <> 49
                    THEN COALESCE(
                        oi.total_amount,
                        oi.unit_price * oi.quantity,
                        0
                    )
                    ELSE 0 END
                ), 0) AS total_sales,
                COALESCE(SUM(
                    CASE WHEN o.status_id = 49
                    THEN COALESCE(
                        oi.total_amount,
                        oi.unit_price * oi.quantity,
                        0
                    )
                    ELSE 0 END
                ), 0) AS cancelled_sales
            ')
            ->groupBy('oi.seller_id');
    }

    private function sellerQuery(Request $request)
    {
        $sales = $this->salesQuery($request);

        return DB::table('seller_store_settings as store')
            ->leftJoin('users as u', 'u.id', '=', 'store.seller_id')
            ->leftJoinSub($sales, 'sales', function ($join) {
                $join->on('sales.seller_id', '=', 'store.seller_id');
            })
            ->when($request->filled('seller_id'), function ($q) use ($request) {
                $q->where('store.seller_id', $request->seller_id);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->search . '%';

                $q->where(function ($sub) use ($search) {
                    $sub->where('store.store_name', 'ilike', $search)
                        ->orWhere('u.name', 'ilike', $search)
                        ->orWhere('u.email', 'ilike', $search);
                });
            })
            ->when($request->has('is_verified'), function ($q) use ($request) {
                $q->where('store.is_verified', filter_var(
                    $request->is_verified,
                    FILTER_VALIDATE_BOOLEAN
                ));
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('store.is_active', filter_var(
                    $request->is_active,
                    FILTER_VALIDATE_BOOLEAN
                ));
            });
    }


    public function summary(Request $request): array
    {
        $row = $this->sellerQuery($request)
            ->selectRaw('
                COUNT(DISTINCT store.id) AS total_sellers,
                COUNT(DISTINCT store.id) FILTER (
                    WHERE store.is_verified = true
                ) AS verified_sellers,
                COUNT(DISTINCT store.id) FILTER (
                    WHERE COALESCE(store.is_verified, false) = false
                ) AS unverified_sellers,
                COUNT(DISTINCT store.id) FILTER (
                    WHERE store.is_active = true
                ) AS active_sellers,
                COUNT(DISTINCT store.id) FILTER (
                    WHERE COALESCE(store.is_active, false) = false
                ) AS inactive_sellers,
                COALESCE(SUM(sales.total_orders), 0) AS total_orders,
                COALESCE(SUM(sales.items_sold), 0) AS items_sold,
                COALESCE(SUM(sales.total_sales), 0) AS total_sales,
                COALESCE(SUM(sales.cancelled_sales), 0) AS cancelled_sales
            ')
            ->first();

        return [
            'total_sellers' => (int) $row->total_sellers,
            'verified_sellers' => (int) $row->verified_sellers,
            'unverified_sellers' => (int) $row->unverified_sellers,
            'active_sellers' => (int) $row->active_sellers,
            'inactive_sellers' => (int) $row->inactive_sellers,
            'total_orders' => (int) $row->total_orders,
            'items_sold' => (int) $row->items_sold,
            'total_sales' => round((float) $row->total_sales, 2),
            'cancelled_sales' => round((float) $row->cancelled_sales, 2),
        ];
    }


    public function sellerList(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        return $this->sellerQuery($request)
            ->select([
                'store.id as store_id',
                'store.seller_id',
                'store.store_name',
                'store.store_slug',
                'store.email as store_email',
                'store.phone as store_phone',
                'store.is_active',
                'store.is_verified',
                'store.verified_at',
                'u.name as seller_name',
                'u.email as seller_email',
                'u.phone as seller_phone',
                'sales.total_orders',
                'sales.delivered_orders',
                'sales.cancelled_orders',
                'sales.items_sold',
                'sales.products_sold',
                'sales.total_sales',
                'sales.cancelled_sales',
            ])
            ->selectRaw('
                COALESCE(sales.total_orders, 0) AS total_orders,
                COALESCE(sales.delivered_orders, 0) AS delivered_orders,
                COALESCE(sales.cancelled_orders, 0) AS cancelled_orders,
                COALESCE(sales.items_sold, 0) AS items_sold,
                COALESCE(sales.products_sold, 0) AS products_sold,
                COALESCE(sales.total_sales, 0) AS total_sales,
                COALESCE(sales.cancelled_sales, 0) AS cancelled_sales
            ')
            ->orderByDesc('total_sales')
            ->orderBy('store.seller_id')
            ->paginate($perPage);
    }

    public function report(Request $request): array
    {
        return [
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'seller_id' => $request->seller_id,
                'search' => $request->search,
                'is_verified' => $request->is_verified,
                'is_active' => $request->is_active,
            ],
            'summary' => $this->summary($request),
            'sellers' => $this->sellerList($request),
        ];
    }
}
