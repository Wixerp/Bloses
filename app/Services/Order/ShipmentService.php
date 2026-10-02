<?php

namespace App\Services\Order;

use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public function createShipment(array $data)
    {
        $sellerId = $this->getSellerId();

        return DB::transaction(function () use ($data, $sellerId) {

            $order = DB::table('orders')->where('orders.id', $data['order_id'])
                ->whereExists(function ($query) use ($sellerId) {
                    $query->select(DB::raw(1))->from('order_items')
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

            $shippingMethod = DB::table('shipping_methods')->where('id', $data['shipping_method_id'])->where('is_active', true)->first();

            if (!$shippingMethod) {
                abort(400, 'Invalid or inactive shipping method.');
            }

            $address = DB::table('order_addresses')->where('order_id', $order->id)->first();

            if (!$address) {
                abort(400, 'Delivery address not found.');
            }

            $zone = DB::table('shipping_zones')->where('is_active', true)
                ->where(function ($query) use ($address) {

                    $query->where(function ($q) use ($address) {
                        $q->where('city_id', $address->city ?? null)
                        ->where('is_nationwide', false);
                    })->orWhere(function ($q) use ($address) {
                        $q->where('state_id', $address->state ?? null)
                        ->whereNull('city_id')->where('is_nationwide', false);
                    })->orWhere('is_nationwide', true);
                })->orderByDesc('is_nationwide')->first();

            if (!$zone) {
                abort(400, 'Shipping zone not found for this delivery address.');
            }

            $rate = DB::table('shipping_rates')->where('shipping_method_id', $shippingMethod->id)->where('shipping_zone_id', $zone->id)->where('is_active', true)->first();

            if (!$rate) {
                abort(400, 'Shipping rate not found for selected method and zone.');
            }

            $itemsAmount = $items->sum(fn ($item) => (float) $item->total_amount);
            $shippingAmount = (float) $rate->base_rate;

            if ($rate->free_shipping_min_amount !== null && $itemsAmount >= (float) $rate->free_shipping_min_amount)
            {
                $shippingAmount = 0;
            }

            $statusId = $this->getShippingStatusId('shipment_created');

            $shipmentId = DB::table('shipments')
                ->insertGetId([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,'status_id' => $statusId,
                    'carrier' => $shippingMethod->name,
                    'tracking_number' => $data['tracking_number'] ?? null,
                    'tracking_url' => null,
                    'sjipping_amount' => $shippingAmount,
                    'shipping_at' => null,
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

            return DB::table('shipments')
                ->leftJoin('master_types as status','status.id','=','shipments.status_id')
                ->leftJoin('shipping_methods as method','method.name','=','shipments.carrier')
                ->where('shipments.id', $shipmentId)
                ->select([
                    'shipments.id','shipments.order_id','shipments.seller_id','shipments.status_id',
                    'status.value as status','status.description as status_name',
                    'shipments.carrier','shipments.tracking_number','shipments.tracking_url',
                    'shipments.sjipping_amount','shipments.shipping_at','shipments.estimated_delivery_at',
                    'shipments.delivered_at','shipments.returned_at',
                    'shipments.notes','shipments.created_at','shipments.updated_at',
                ])->first();
        });
    }
}
