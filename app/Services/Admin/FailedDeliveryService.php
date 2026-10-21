<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FailedDeliveryService
{
    private const SHIPPING_STATUS_ID = 47;

    public function index(array $filters = []): array
    {
        $query = DB::table('shipments as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('users as seller', 'seller.id', '=', 's.seller_id')
            ->leftJoin('master_types as st', 'st.id', '=', 's.status_id')
            ->leftJoin(
                'shipping_methods as sm',
                'sm.id',
                '=',
                's.shipping_method_id'
            )
            ->where('s.status_id', self::SHIPPING_STATUS_ID)
            ->whereRaw(
                "LOWER(COALESCE(s.notes, '')) ~
                '(fail|undeliver|delivery attempt|not delivered)'"
            )
            ->select([
                's.id',
                's.order_id',
                's.seller_id',
                's.status_id',
                's.carrier',
                's.tracking_number',
                's.tracking_url',
                's.shipping_amount',
                's.payment_method_id',
                's.shipping_method_id',
                's.shipped_at',
                's.estimated_delivery_at',
                's.delivered_at',
                's.returned_at',
                's.notes',
                's.created_at',
                's.updated_at',
                'st.key as status_type',
                'st.value as status_value',
                'st.description as status',
                'sm.name as shipping_method',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                'seller.name as seller_name',
                'seller.email as seller_email',
            ]);

        if (!empty($filters['shipment_id'])) {
            $query->where('s.id', $filters['shipment_id']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('s.order_id', $filters['order_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($q) use ($search) {
                $q->where('s.tracking_number', 'ILIKE', $search)
                    ->orWhere('s.carrier', 'ILIKE', $search)
                    ->orWhere('buyer.name', 'ILIKE', $search)
                    ->orWhere('seller.name', 'ILIKE', $search)
                    ->orWhereRaw(
                        'CAST(s.order_id AS TEXT) ILIKE ?',
                        [$search]
                    )
                    ->orWhere('s.notes', 'ILIKE', $search);
            });
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 10), 1),
            100
        );

        $paginator = $query
            ->orderByDesc('s.updated_at')
            ->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function show(int $id): object
    {
        $shipment = DB::table('shipments as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('users as seller', 'seller.id', '=', 's.seller_id')
            ->leftJoin('master_types as st', 'st.id', '=', 's.status_id')
            ->leftJoin(
                'shipping_methods as sm',
                'sm.id',
                '=',
                's.shipping_method_id'
            )
            ->where('s.id', $id)
            ->select([
                's.*',
                'st.key as status_type',
                'st.value as status_value',
                'st.description as status',
                'sm.name as shipping_method',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                'seller.name as seller_name',
                'seller.email as seller_email',
            ])
            ->first();

        if (!$shipment) {
            abort(404, 'Shipment not found.');
        }

        $shipment->items = DB::table('shipment_items as si')
            ->leftJoin(
                'order_items as oi',
                'oi.id',
                '=',
                'si.order_item_id'
            )
            ->where('si.shipment_id', $id)
            ->select('si.*', 'oi.product_id', 'oi.quantity')
            ->get();

        return $shipment;
    }

    public function updateStatus(
        int $id,
        int $statusId,
        ?string $notes = null
    ): object {
        $shipment = DB::table('shipments')
            ->where('id', $id)
            ->first();

        if (!$shipment) {
            abort(404, 'Shipment not found.');
        }

        // Only accept actual order_status entries.
        $validStatus = DB::table('master_types')
            ->where('id', $statusId)
            ->where('key', 'order_status')
            ->exists();

        if (!$validStatus) {
            throw ValidationException::withMessages([
                'status_id' => [
                    'Status ID must belong to order_status.',
                ],
            ]);
        }

        $update = [
            'status_id' => $statusId,
            'updated_at' => now(),
        ];

        if ($notes !== null) {
            $update['notes'] = $notes;
        }

        if ($statusId === 48) {
            $update['delivered_at'] = now();
        }

        DB::table('shipments')
            ->where('id', $id)
            ->update($update);

        return $this->show($id);
    }
}
