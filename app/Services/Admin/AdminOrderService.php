<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class AdminOrderService
{
    public function getOrders(array $filters = [])
    {
        $query = DB::table('orders as o')
            ->leftJoin('users as customer', 'customer.id', '=', 'o.user_id')
            ->leftJoin('master_types as os', 'os.id', '=', 'o.status_id')
            ->leftJoin('master_types as pm', 'pm.id', '=', 'o.payment_method_id')
            ->select([
                'o.id',
                'o.order_number',
                'o.user_id as customer_id',
                'customer.name as customer_name',
                'customer.email as customer_email',
                'customer.phone as customer_phone',
                'o.status_id',
                'os.description as order_status',
                'o.payment_method_id',
                'pm.description as payment_method',
                'o.subtotal',
                'o.discount_amount',
                'o.tax_amount',
                'o.shipping_amount',
                'o.total_amount',
                'o.customer_note',
                'o.placed_at',
                'o.created_at',
            ]);

        if (!empty($filters['order_id'])) {
            $query->where('o.id', $filters['order_id']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('o.user_id', $filters['customer_id']);
        }

        if (!empty($filters['status_id'])) {
            $query->where('o.status_id', $filters['status_id']);
        }

        if (!empty($filters['seller_id'])) {
            $sellerId = $filters['seller_id'];

            $query->whereExists(function ($sub) use ($sellerId) {
                $sub->select(DB::raw(1))
                    ->from('order_items as oi')
                    ->whereColumn('oi.order_id', 'o.id')
                    ->where('oi.seller_id', $sellerId);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate(
                'o.created_at',
                '>=',
                $filters['date_from']
            );
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate(
                'o.created_at',
                '<=',
                $filters['date_to']
            );
        }

        $orders = $query
            ->orderByDesc('o.id')
            ->paginate($filters['per_page'] ?? 15);

        $orderIds = $orders->getCollection()->pluck('id');

        if ($orderIds->isEmpty()) {
            return $orders;
        }

        $items = DB::table('order_items as oi')
            ->leftJoin('sellers as s', 's.id', '=', 'oi.seller_id')
            ->leftJoin('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin(
                'seller_store_settings as ss',
                'ss.seller_id',
                '=',
                's.id'
            )
            ->whereIn('oi.order_id', $orderIds)
            ->select([
                'oi.id as order_item_id',
                'oi.order_id',
                'oi.product_id',
                'oi.product_variant_id',
                'oi.product_name',
                'oi.variant_name',
                'oi.sku',
                'oi.unit_price',
                'oi.quantity',
                'oi.discount_amount',
                'oi.tax_amount',
                'oi.shipping_amount',
                'oi.total_amount as item_total',
                's.id as seller_id',
                'su.name as seller_name',
                'su.email as seller_email',
                'su.phone as seller_phone',
                'ss.store_name',
            ])
            ->orderBy('oi.id')
            ->get()
            ->groupBy('order_id');

        $payments = DB::table('payments as p')
            ->leftJoin(
                'master_types as ps',
                'ps.id',
                '=',
                'p.status_id'
            )
            ->leftJoin(
                'master_types as pmethod',
                'pmethod.id',
                '=',
                'p.payment_method_id'
            )
            ->whereIn('p.order_id', $orderIds)
            ->select([
                'p.id',
                'p.order_id',
                'p.status_id',
                'ps.description as payment_status',
                'p.payment_method_id',
                'pmethod.description as payment_method',
            ])
            ->orderByDesc('p.id')
            ->get()
            ->unique('order_id')
            ->keyBy('order_id');

        $orders->getCollection()->transform(
            function ($order) use ($items, $payments) {
                $orderItems = $items->get($order->id, collect());

                $order->sellers = $orderItems
                    ->groupBy('seller_id')
                    ->map(function ($sellerItems) {
                        $first = $sellerItems->first();

                        return [
                            'seller_id' => $first->seller_id,
                            'seller_name' => $first->seller_name,
                            'seller_email' => $first->seller_email,
                            'seller_phone' => $first->seller_phone,
                            'store_name' => $first->store_name,
                            'products' => $sellerItems->map(
                                function ($item) {
                                    return [
                                        'order_item_id' => $item->order_item_id,
                                        'product_id' => $item->product_id,
                                        'product_name' => $item->product_name,
                                        'variant_id' => $item->product_variant_id,
                                        'variant_name' => $item->variant_name,
                                        'sku' => $item->sku,
                                        'unit_price' => $item->unit_price,
                                        'quantity' => $item->quantity,
                                        'discount_amount' => $item->discount_amount,
                                        'tax_amount' => $item->tax_amount,
                                        'shipping_amount' => $item->shipping_amount,
                                        'total_amount' => $item->item_total,
                                    ];
                                }
                            )->values(),
                        ];
                    })->values();

                $payment = $payments->get($order->id);

                $order->payment = [
                    'payment_id' => $payment->id ?? null,
                    'payment_method' => $payment->payment_method
                        ?? $order->payment_method,
                    'payment_status' => $payment
                        ? ($payment->payment_status ?? 'Unknown')
                        : 'Pending',
                ];

                return $order;
            }
        );

        return $orders;
    }
}
