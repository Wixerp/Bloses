<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\DB;
use App\Services\Notification\NotificationService;

class ShipmentService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}
    public function getShipments(array $filters = [])
    {
        $sellerId = $this->getSellerId();
        $query = DB::table('shipments')
            ->leftJoin('master_types as status', 'status.id', '=', 'shipments.status_id')
            ->leftJoin('orders', 'orders.id', '=', 'shipments.order_id')
            ->leftJoin('sellers', 'sellers.id', '=','shipments.seller_id')
            ->leftJoin('users as seller_user', 'seller_user.id', '=', 'sellers.user_id')
            ->leftJoin('users as buyer_user', 'buyer_user.id', '=', 'orders.user_id')
            ->where('shipments.seller_id', $sellerId)
            ->select([
                'shipments.id', 'shipments.order_id', 'orders.order_number', 'orders.user_id as buyer_id',
                'buyer_user.name as buyer_name', 'shipments.status_id', 'status.value as status', 'status.description as status_name',
                'shipments.shipping_method_id', 'shipments.payment_method_id', 'shipments.carrier', 'shipments.tracking_number',
                'shipments.tracking_url', 'shipments.shipping_amount', 'shipments.shipped_at', 'shipments.estimated_delivery_at',
                'shipments.delivered_at', 'shipments.returned_at', 'shipments.notes', 'shipments.created_at', 'shipments.updated_at',
            ]);

        if (!empty($filters['id'])) {
            $query->where('shipments.id', $filters['id']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('shipments.order_id', $filters['order_id']);
        }

        if (!empty($filters['status_id'])) {
            $query->where('shipments.status_id', $filters['status_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('shipments.tracking_number', 'ILIKE', "%{$search}%")
                ->orWhere('shipments.carrier', 'ILIKE', "%{$search}%")
                ->orWhere('status.value', 'ILIKE', "%{$search}%")
                ->orWhere('status.description', 'ILIKE', "%{$search}%")
                ->orWhere('seller_user.name', 'ILIKE', "%{$search}%")
                ->orWhere('buyer_user.name', 'ILIKE', "%{$search}%");
            });
        }

        return $query->orderByDesc('shipments.id')->get();
    }

    public function createShipment(array $data)
    {
        $sellerId = $this->getSellerId();

        return DB::transaction(function () use ($data, $sellerId) {

            $order = DB::table('orders')
                ->where('orders.id', $data['order_id'])
                ->whereExists(function ($query) use ($sellerId) {
                    $query->select(DB::raw(1))
                        ->from('order_items')
                        ->whereColumn('order_items.order_id', 'orders.id')
                        ->where('order_items.seller_id', $sellerId);
                })->first();

            if (!$order) {
                abort(404, 'Order not found or this order does not belong to you.');
            }

            $items = DB::table('order_items')->where('order_id', $order->id)->where('seller_id', $sellerId)->get();

            if ($items->isEmpty()) {
                abort(400, 'No order items found for this seller.');
            }

            $shippingMethod = DB::table('master_types')->where('id', $data['shipping_method_id'])->first();

            if (!$shippingMethod) {
                abort(400, 'Invalid shipping method.');
            }

            $address = DB::table('order_addresses')->where('order_id', $order->id)->first();

            if (!$address) {
                abort(400, 'Delivery address not found.');
            }

            $shippingAmount = 0;
            $statusId = DB::table('master_types')->where('id', 47)->value('id');

            if (!$statusId) {
                abort(500, 'Shipment status not found.');
            }

            $shipmentId = DB::table('shipments')
                ->insertGetId([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'status_id' => $statusId,
                    'shipping_method_id' => $data['shipping_method_id'],
                    'payment_method_id' => $order->payment_method_id,
                    'carrier' => $shippingMethod->value,
                    'tracking_number' => $data['tracking_number'] ?? null,
                    'tracking_url' => null,
                    'shipping_amount' => $shippingAmount,
                    'shipped_at' => null,
                    'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                    'delivered_at' => null,
                    'returned_at' => null,
                    'notes' => $data['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach ($items as $item) {
                DB::table('shipment_items')
                    ->insert([
                        'shipment_id' => $shipmentId,
                        'order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
            $usersId = auth()->id();
            $this->notificationService->create([
                'user_id' => $usersId,
                'type' => 'shipment',
                'order_id' => $order->id,
                'shipment_id' => $shipmentId,
                'title' => 'Shipment Created',
                'message' => "Your order {$order->order_number} has been shipped via {$shippingMethod->value}.",
                'action_url' => "/buyer/orders/{$order->id}",
            ]);

            return DB::table('shipments')
                ->leftJoin('master_types as status','status.id','=','shipments.status_id')
                ->where('shipments.id', $shipmentId)
                ->select([
                    'shipments.id','shipments.order_id','shipments.seller_id','shipments.status_id',
                    'status.value as status','status.description as status_name','shipments.carrier',
                    'shipments.tracking_number','shipments.tracking_url','shipments.shipping_amount',
                    'shipments.shipped_at','shipments.estimated_delivery_at','shipments.delivered_at',
                    'shipments.returned_at','shipments.notes','shipments.created_at','shipments.updated_at',
                ])->first();
        });
    }

    private function getSellerId(): int
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        return (int) $sellerId;
    }

    private function getShippingStatusId(string $value): int {

        $statusId = DB::table('master_types')->where('value', $value)->value('id');

        if (!$statusId) {
            abort(500, "Shipping status '{$value}' not found.");
        }

        return (int) $statusId;
    }
}
