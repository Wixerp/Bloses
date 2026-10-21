<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    private const CANCELLED_STATUS_ID = 49;

    private function filters(Request $request)
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'o.status_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where(function ($q) {
                $q->whereNull('st.key')
                  ->orWhere('st.key', 'order_status');
            })
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
            ->when($request->filled('status_id'), function ($q) use ($request) {
                $q->where('o.status_id', $request->status_id);
            })
            ->when($request->filled('seller_id'), function ($q) use ($request) {
                $q->where('oi.seller_id', $request->seller_id);
            })
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->where('oi.product_id', $request->product_id);
            });
    }

    public function summary(Request $request): array
    {
        $row = $this->filters($request)
            ->selectRaw('
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(oi.quantity), 0) AS total_items,
                COALESCE(SUM(
                    CASE WHEN o.status_id <> ?
                    THEN COALESCE(oi.total_amount, oi.unit_price * oi.quantity, 0)
                    ELSE 0 END
                ), 0) AS total_sales,
                COALESCE(SUM(
                    CASE WHEN o.status_id = ?
                    THEN COALESCE(oi.total_amount, oi.unit_price * oi.quantity, 0)
                    ELSE 0 END
                ), 0) AS cancelled_sales,
                COALESCE(SUM(oi.discount_amount), 0) AS total_discount,
                COALESCE(SUM(oi.tax_amount), 0) AS total_tax,
                COALESCE(SUM(oi.shipping_amount), 0) AS total_shipping,
                COUNT(DISTINCT CASE WHEN o.status_id = ?
                    THEN o.id END) AS cancelled_orders,
                COUNT(DISTINCT CASE WHEN o.status_id = ?
                    THEN o.id END) AS delivered_orders
            ', [
                self::CANCELLED_STATUS_ID,
                self::CANCELLED_STATUS_ID,
                self::CANCELLED_STATUS_ID,
                48
            ])
            ->first();

        return [
            'total_orders' => (int) $row->total_orders,
            'total_items' => (int) $row->total_items,
            'total_sales' => round((float) $row->total_sales, 2),
            'cancelled_sales' => round((float) $row->cancelled_sales, 2),
            'total_discount' => round((float) $row->total_discount, 2),
            'total_tax' => round((float) $row->total_tax, 2),
            'total_shipping' => round((float) $row->total_shipping, 2),
            'cancelled_orders' => (int) $row->cancelled_orders,
            'delivered_orders' => (int) $row->delivered_orders,
        ];
    }

    public function salesByStatus(Request $request)
    {
        return $this->filters($request)
            ->selectRaw('
                o.status_id,
                COALESCE(st.value, st.description, \'Unknown\') AS status,
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(oi.quantity), 0) AS total_items,
                COALESCE(SUM(
                    COALESCE(oi.total_amount, oi.unit_price * oi.quantity, 0)
                ), 0) AS item_sales
            ')
            ->groupBy('o.status_id', 'st.value', 'st.description')
            ->orderBy('o.status_id')
            ->get()
            ->map(function ($row) {
                $row->total_orders = (int) $row->total_orders;
                $row->total_items = (int) $row->total_items;
                $row->item_sales = round((float) $row->item_sales, 2);

                return $row;
            });
    }

    public function sellerSales(Request $request)
    {
        return $this->filters($request)
            ->leftJoin(
                'seller_store_settings as store',
                'store.seller_id',
                '=',
                'oi.seller_id'
            )
            ->selectRaw('
                oi.seller_id,
                COALESCE(
                    store.store_name,
                    \'Unknown Store\'
                ) AS store_name,
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(oi.quantity), 0) AS total_items,
                COALESCE(SUM(
                    CASE WHEN o.status_id <> ?
                    THEN COALESCE(oi.total_amount, oi.unit_price * oi.quantity, 0)
                    ELSE 0 END
                ), 0) AS total_sales
            ', [self::CANCELLED_STATUS_ID])
            ->groupBy('oi.seller_id', 'store.store_name')
            ->orderByDesc('total_sales')
            ->get()
            ->map(function ($row) {
                $row->seller_id = (int) $row->seller_id;
                $row->total_orders = (int) $row->total_orders;
                $row->total_items = (int) $row->total_items;
                $row->total_sales = round((float) $row->total_sales, 2);

                return $row;
            });
    }

    public function productSales(Request $request)
    {
        return $this->filters($request)
            ->selectRaw('
                oi.product_id,
                oi.product_name,
                oi.seller_id,
                COALESCE(p.product_code, oi.sku) AS sku,
                COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(
                    CASE WHEN o.status_id <> ?
                    THEN COALESCE(oi.total_amount, oi.unit_price * oi.quantity, 0)
                    ELSE 0 END
                ), 0) AS total_sales
            ', [self::CANCELLED_STATUS_ID])
            ->groupBy(
                'oi.product_id',
                'oi.product_name',
                'oi.seller_id',
                'p.product_code',
                'oi.sku'
            )
            ->orderByDesc('total_sales')
            ->get()
            ->map(function ($row) {
                $row->product_id = (int) $row->product_id;
                $row->seller_id = (int) $row->seller_id;
                $row->total_quantity = (int) $row->total_quantity;
                $row->total_orders = (int) $row->total_orders;
                $row->total_sales = round((float) $row->total_sales, 2);

                return $row;
            });
    }

    public function orderSales(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $orders = $this->filters($request)
            ->selectRaw('
                o.id,
                o.order_number,
                o.user_id AS customer_id,
                u.name AS customer_name,
                u.email AS customer_email,
                o.status_id,
                COALESCE(st.value, st.description, \'Unknown\') AS status,
                o.payment_method_id,
                pm.value AS payment_method,
                o.subtotal,
                o.discount_amount,
                o.tax_amount,
                o.shipping_amount,
                o.total_amount,
                COALESCE(o.placed_at, o.created_at) AS order_date
            ')
            ->leftJoin(
                'master_types as pm',
                function ($join) {
                    $join->on('pm.id', '=', 'o.payment_method_id')
                         ->where('pm.key', '=', 'payment_method');
                }
            )
            ->groupBy(
                'o.id',
                'o.order_number',
                'o.user_id',
                'u.name',
                'u.email',
                'o.status_id',
                'st.value',
                'st.description',
                'o.payment_method_id',
                'pm.value',
                'o.subtotal',
                'o.discount_amount',
                'o.tax_amount',
                'o.shipping_amount',
                'o.total_amount',
                'o.placed_at',
                'o.created_at'
            )
            ->orderByDesc('order_date')
            ->paginate($perPage);

        return $orders;
    }

    public function report(Request $request): array
    {
        return [
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'seller_id' => $request->seller_id,
                'product_id' => $request->product_id,
                'status_id' => $request->status_id,
            ],
            'summary' => $this->summary($request),
            'sales_by_status' => $this->salesByStatus($request),
            'seller_sales' => $this->sellerSales($request),
            'product_sales' => $this->productSales($request),
            'orders' => $this->orderSales($request),
        ];
    }
}
