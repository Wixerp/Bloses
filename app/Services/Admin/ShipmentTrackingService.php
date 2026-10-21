<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentTrackingService
{
    public function track(Request $request): array
    {
        $request->validate([
            'shipment_id' => 'nullable|integer|min:1|required_without:tracking_number',
            'tracking_number' => 'nullable|string|max:255|required_without:shipment_id',
        ]);

        $query = DB::table('shipments as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('users as seller', 'seller.id', '=', 's.seller_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 's.status_id')
            ->leftJoin('shipping_methods as sm', 'sm.id', '=', 's.shipping_method_id')
            ->leftJoin('master_types as pm', 'pm.id', '=', 's.payment_method_id')
            ->select([
                's.id as shipment_id',
                's.order_id',
                'o.order_number',
                'buyer.id as buyer_id',
                'buyer.name as buyer_name',
                'seller.id as seller_id',
                'seller.name as seller_name',
                's.status_id',
                'mt.description as status',
                's.carrier',
                's.tracking_number',
                's.tracking_url',
                's.shipping_amount',
                's.payment_method_id',
                'pm.description as payment_method',
                's.shipping_method_id',
                'sm.name as shipping_method',
                's.shipped_at',
                's.estimated_delivery_at',
                's.delivered_at',
                's.returned_at',
                's.notes',
                's.created_at',
                's.updated_at',
            ]);

        if ($request->filled('shipment_id')) {
            $query->where('s.id', $request->shipment_id);
        } else {
            $query->where(
                's.tracking_number',
                $request->tracking_number
            );
        }

        $shipment = $query->first();

        if (!$shipment) {
            return [
                'found' => false,
                'shipment' => null,
                'items' => [],
            ];
        }

        $items = DB::table('shipment_items as si')
            ->join('order_items as oi', 'oi.id', '=', 'si.order_item_id')
            ->where('si.shipment_id', $shipment->shipment_id)
            ->select([
                'si.id as shipment_item_id',
                'si.order_item_id',
                'oi.product_id',
                'oi.product_name',
                'oi.variant_name',
                'oi.sku',
                'oi.unit_price',
                'si.quantity',
                'oi.total_amount',
            ])
            ->get();

        return [
            'found' => true,
            'shipment' => $shipment,
            'items' => $items,
        ];
    }
}
