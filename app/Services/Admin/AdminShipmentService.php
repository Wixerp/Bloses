<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminShipmentService
{
    public function getAll(Request $request): array
    {
        $request->validate([
            'shipment_id' => 'nullable|integer|min:1',
            'order_id' => 'nullable|integer|min:1',
            'seller_id' => 'nullable|integer|min:1',
            'status_id' => 'nullable|integer|min:1',
            'payment_method_id' => 'nullable|integer|min:1',
            'shipping_method_id' => 'nullable|integer|min:1',
            'tracking_number' => 'nullable|string|max:255',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('shipments as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('users as seller', 'seller.id', '=', 's.seller_id')
            ->leftJoin('master_types as shipment_status', 'shipment_status.id', '=', 's.status_id')
            ->leftJoin('master_types as order_status', 'order_status.id', '=', 'o.status_id')
            ->leftJoin('master_types as payment_method', 'payment_method.id', '=', 's.payment_method_id')
            ->leftJoin('shipping_methods as sm', 'sm.id', '=', 's.shipping_method_id')
            ->select([
                's.id as shipment_id',
                's.order_id',
                'o.order_number',
                's.seller_id',
                'seller.name as seller_name',
                'o.user_id as buyer_id',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                's.status_id',
                'shipment_status.description as shipment_status',
                'o.status_id as order_status_id',
                'order_status.description as order_status',
                's.payment_method_id',
                'payment_method.description as payment_method',
                's.shipping_method_id',
                'sm.name as shipping_method',
                'sm.code as shipping_method_code',
                's.carrier',
                's.tracking_number',
                's.tracking_url',
                's.shipping_amount',
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
        }

        if ($request->filled('order_id')) {
            $query->where('s.order_id', $request->order_id);
        }

        if ($request->filled('seller_id')) {
            $query->where('s.seller_id', $request->seller_id);
        }

        if ($request->filled('status_id')) {
            $query->where('s.status_id', $request->status_id);
        }

        if ($request->filled('payment_method_id')) {
            $query->where('s.payment_method_id', $request->payment_method_id);
        }

        if ($request->filled('shipping_method_id')) {
            $query->where('s.shipping_method_id', $request->shipping_method_id);
        }

        if ($request->filled('tracking_number')) {
            $query->where(
                's.tracking_number',
                'ILIKE',
                '%' . $request->tracking_number . '%'
            );
        }

        if ($request->filled('from_date')) {
            $query->whereDate('s.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('s.created_at', '<=', $request->to_date);
        }

        $shipments = $query
            ->orderByDesc('s.id')
            ->paginate($request->integer('per_page', 10));

        $shipmentIds = collect($shipments->items())
            ->pluck('shipment_id')
            ->all();

        $items = DB::table('shipment_items as si')
            ->join('order_items as oi', 'oi.id', '=', 'si.order_item_id')
            ->whereIn('si.shipment_id', $shipmentIds)
            ->select([
                'si.id as shipment_item_id',
                'si.shipment_id',
                'si.order_item_id',
                'oi.product_id',
                'oi.product_variant_id',
                'oi.product_name',
                'oi.variant_name',
                'oi.sku',
                'oi.unit_price',
                'si.quantity',
                'oi.discount_amount',
                'oi.tax_amount',
                'oi.total_amount',
            ])
            ->orderBy('si.id')
            ->get()
            ->groupBy('shipment_id');

        $data = collect($shipments->items())->map(function ($shipment) use ($items) {
            $shipment->items = $items->get($shipment->shipment_id, collect())
                ->values();

            return $shipment;
        })->values();

        return [
            'shipments' => $data,
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
                'from' => $shipments->firstItem(),
                'to' => $shipments->lastItem(),
            ],
        ];
    }
}
