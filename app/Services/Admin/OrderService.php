<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Get all orders for Admin listing.
     */
    public function getOrders(array $filters = [])
    {
        $query = DB::table('orders')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'orders.user_id')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->leftJoin('master_types as payment_method','payment_method.id','=','orders.payment_method_id')
            ->select([
                'orders.*',
                'buyer.name as buyer_name','buyer.email as buyer_email','status.description as status_name','payment_method.description as payment_method_name',
            ])
            ->selectSub(function ($q) {
                $q->from('order_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('order_items.order_id', 'orders.id');
            }, 'items_count');

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('buyer.name', 'ilike', "%{$search}%")
                    ->orWhere('buyer.email', 'ilike', "%{$search}%")
                    ->orWhere('orders.order_number', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['status_id'])) {
            $query->where('orders.status_id', $filters['status_id']);
        }

        return $query->orderByDesc('orders.created_at')->get();
    }

    public function getOrderById(int $id)
    {
        $order = DB::table('orders')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'orders.user_id')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->leftJoin('master_types as payment_method','payment_method.id','=','orders.payment_method_id')
            ->where('orders.id', $id)
            ->select([
                'orders.*',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                'buyer.phone as buyer_phone',
                'status.description as status_name',
                'payment_method.description as payment_method_name',
            ])
            ->first();

        if (!$order) {
            return null;
        }

        // Order products and their sellers
        $items = DB::table('order_items')
            ->leftJoin(
                'products',
                'products.id',
                '=',
                'order_items.product_id'
            )
            ->leftJoin(
                'users as seller',
                'seller.id',
                '=',
                'order_items.seller_id'
            )
            ->where('order_items.order_id', $id)
            ->select([
                'order_items.*',
                'products.name as product_name',
                'seller.name as seller_name',
            ])
            ->get();

        // Shipping address, if stored separately
        $address = DB::table('order_addresses')
            ->where('order_id', $id)
            ->first();

        // Shipments for this order
        $shipments = DB::table('shipments')
            ->leftJoin(
                'master_types as status',
                'status.id',
                '=',
                'shipments.status_id'
            )
            ->where('shipments.order_id', $id)
            ->select([
                'shipments.*',
                'status.description as status_name',
            ])
            ->orderByDesc('shipments.id')
            ->get();

        return [
            'order' => $order,
            'items' => $items,
            'address' => $address,
            'shipments' => $shipments,
        ];
    }
}
