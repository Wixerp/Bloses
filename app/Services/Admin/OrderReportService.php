<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderReportService
{
    private function baseQuery(Request $request)
    {
        return DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('master_types as st', function ($join) {
                $join->on('st.id', '=', 'o.status_id')
                    ->where('st.key', '=', 'order_status');
            })
            ->leftJoin('master_types as pm', function ($join) {
                $join->on('pm.id', '=', 'o.payment_method_id')
                    ->where('pm.key', '=', 'payment_method');
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereRaw(
                    'COALESCE(o.placed_at, o.created_at) >= ?',
                    [$request->date_from . ' 00:00:00']
                );
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereRaw(
                    'COALESCE(o.placed_at, o.created_at) < ?',
                    [
                        date(
                            'Y-m-d',
                            strtotime($request->date_to . ' +1 day')
                        ) . ' 00:00:00'
                    ]
                );
            })
            ->when($request->filled('order_number'), function ($query) use ($request) {
                $query->where(
                    'o.order_number',
                    'ilike',
                    '%' . $request->order_number . '%'
                );
            })
            ->when($request->filled('customer_id'), function ($query) use ($request) {
                $query->where('o.user_id', $request->customer_id);
            })
            ->when($request->filled('status_id'), function ($query) use ($request) {
                $query->where('o.status_id', $request->status_id);
            })
            ->when($request->filled('payment_method_id'), function ($query) use ($request) {
                $query->where('o.payment_method_id', $request->payment_method_id);
            })
            ->when($request->filled('seller_id'), function ($query) use ($request) {
                $query->whereExists(function ($subquery) use ($request) {
                    $subquery->selectRaw('1')
                        ->from('order_items as oi')
                        ->whereColumn('oi.order_id', 'o.id')
                        ->where('oi.seller_id', $request->seller_id);
                });
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->whereExists(function ($subquery) use ($request) {
                    $subquery->selectRaw('1')
                        ->from('order_items as oi')
                        ->whereColumn('oi.order_id', 'o.id')
                        ->where('oi.product_id', $request->product_id);
                });
            });
    }

    public function summary(Request $request): array
    {
        $row = $this->baseQuery($request)
            ->selectRaw('
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(o.total_amount), 0) AS total_order_value,
                COALESCE(SUM(o.subtotal), 0) AS total_subtotal,
                COALESCE(SUM(o.discount_amount), 0) AS total_discount,
                COALESCE(SUM(o.tax_amount), 0) AS total_tax,
                COALESCE(SUM(o.shipping_amount), 0) AS total_shipping,
                COUNT(DISTINCT CASE WHEN o.status_id = 46
                    THEN o.id END) AS processing_orders,
                COUNT(DISTINCT CASE WHEN o.status_id = 47
                    THEN o.id END) AS shipping_orders,
                COUNT(DISTINCT CASE WHEN o.status_id = 48
                    THEN o.id END) AS delivered_orders,
                COUNT(DISTINCT CASE WHEN o.status_id = 49
                    THEN o.id END) AS cancelled_orders,
                COALESCE(SUM(CASE WHEN o.status_id = 49
                    THEN o.total_amount ELSE 0 END), 0) AS cancelled_value
            ')
            ->first();

        return [
            'total_orders' => (int) $row->total_orders,
            'total_order_value' => round((float) $row->total_order_value, 2),
            'total_subtotal' => round((float) $row->total_subtotal, 2),
            'total_discount' => round((float) $row->total_discount, 2),
            'total_tax' => round((float) $row->total_tax, 2),
            'total_shipping' => round((float) $row->total_shipping, 2),
            'processing_orders' => (int) $row->processing_orders,
            'shipping_orders' => (int) $row->shipping_orders,
            'delivered_orders' => (int) $row->delivered_orders,
            'cancelled_orders' => (int) $row->cancelled_orders,
            'cancelled_value' => round((float) $row->cancelled_value, 2),
        ];
    }

    public function statusBreakdown(Request $request)
    {
        return $this->baseQuery($request)
            ->selectRaw('
                o.status_id,
                COALESCE(st.value, st.description, \'Unknown\') AS status,
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(o.total_amount), 0) AS total_order_value
            ')
            ->groupBy('o.status_id', 'st.value', 'st.description')
            ->orderBy('o.status_id')
            ->get()
            ->map(function ($row) {
                $row->status_id = (int) $row->status_id;
                $row->total_orders = (int) $row->total_orders;
                $row->total_order_value = round(
                    (float) $row->total_order_value,
                    2
                );

                return $row;
            });
    }

    public function orderList(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $orders = $this->baseQuery($request)
            ->select([
                'o.id',
                'o.order_number',
                'o.user_id as customer_id',
                'u.name as customer_name',
                'u.email as customer_email',
                'u.phone as customer_phone',
                'o.status_id',
                'st.value as status',
                'o.payment_method_id',
                'pm.value as payment_method',
                'o.subtotal',
                'o.discount_amount',
                'o.tax_amount',
                'o.shipping_amount',
                'o.total_amount',
                'o.customer_note',
                'o.placed_at',
                'o.created_at',
                'o.confirmed_at',
                'o.cancelled_at',
                'o.completed_at',
            ])
            ->orderByRaw('COALESCE(o.placed_at, o.created_at) DESC')
            ->orderByDesc('o.id')
            ->paginate($perPage);

        $orderIds = $orders->getCollection()
            ->pluck('id')
            ->all();

        $items = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->select([
                'id',
                'order_id',
                'product_id',
                'product_variant_id',
                'seller_id',
                'product_name',
                'variant_name',
                'sku',
                'unit_price',
                'quantity',
                'discount_amount',
                'tax_amount',
                'shipping_amount',
                'total_amount',
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        $orders->getCollection()->transform(function ($order) use ($items) {
            $order->items = $items->get($order->id, collect())->values();

            return $order;
        });

        return $orders;
    }

    public function report(Request $request): array
    {
        return [
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'order_number' => $request->order_number,
                'customer_id' => $request->customer_id,
                'status_id' => $request->status_id,
                'payment_method_id' => $request->payment_method_id,
                'seller_id' => $request->seller_id,
                'product_id' => $request->product_id,
            ],
            'summary' => $this->summary($request),
            'status_breakdown' => $this->statusBreakdown($request),
            'orders' => $this->orderList($request),
        ];
    }
}
