<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminCodSettlementService
{
    private const COD_PAYMENT_METHOD_ID = 44;

    private function statusId(string $value): int
    {
        $id = DB::table('master_types')
            ->where('key', 'cod_settlement_status')
            ->where('value', $value)
            ->where('is_active', true)
            ->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'status' => ["COD settlement status '{$value}' not found."],
            ]);
        }

        return (int) $id;
    }

    /**
     * COD amount for the items included in a particular shipment.
     * Uses order-item totals proportionally for partial shipments.
     */
    private function expectedAmount(int $shipmentId): float
    {
        $amount = DB::table('shipment_items as si')
            ->join('order_items as oi', 'oi.id', '=', 'si.order_item_id')
            ->where('si.shipment_id', $shipmentId)
            ->selectRaw(
                'COALESCE(SUM(
                    si.quantity * oi.total_amount
                    / NULLIF(oi.quantity, 0)
                ), 0) AS amount'
            )
            ->value('amount');

        return round((float) $amount, 2);
    }

    public function summary(): array
    {
        $base = DB::table('shipments as sh')
            ->join('orders as o', 'o.id', '=', 'sh.order_id')
            ->where('o.payment_method_id', self::COD_PAYMENT_METHOD_ID);

        $totalShipments = (clone $base)->count('sh.id');

        $delivered = (clone $base)
            ->whereNotNull('sh.delivered_at')
            ->count('sh.id');

        $remittance = DB::table('cod_remittances as cr')
            ->join('shipments as sh', 'sh.id', '=', 'cr.shipment_id')
            ->join('orders as o', 'o.id', '=', 'sh.order_id')
            ->where('o.payment_method_id', self::COD_PAYMENT_METHOD_ID);

        $totals = (clone $remittance)
            ->selectRaw('
                COALESCE(SUM(cr.expected_amount), 0) AS expected_amount,
                COALESCE(SUM(cr.collected_amount), 0) AS collected_amount,
                COALESCE(SUM(cr.remitted_amount), 0) AS remitted_amount
            ')
            ->first();

        $pendingStatus = $this->statusId('pending');
        $disputedStatus = $this->statusId('disputed');

        return [
            'total_cod_shipments' => $totalShipments,
            'delivered_shipments' => $delivered,
            'pending_remittances' => (clone $remittance)
                ->where('cr.status_id', $pendingStatus)
                ->count('cr.id'),
            'disputed_remittances' => (clone $remittance)
                ->where('cr.status_id', $disputedStatus)
                ->count('cr.id'),
            'expected_amount' => round((float) $totals->expected_amount, 2),
            'collected_amount' => round((float) $totals->collected_amount, 2),
            'remitted_amount' => round((float) $totals->remitted_amount, 2),
            'outstanding_amount' => round(
                max(
                    0,
                    (float) $totals->collected_amount
                    - (float) $totals->remitted_amount
                ),
                2
            ),
            'currency' => 'PKR',
        ];
    }

    public function index(array $filters = []): array
    {
        $expectedSubquery = DB::table('shipment_items as si')
            ->join('order_items as oi', 'oi.id', '=', 'si.order_item_id')
            ->selectRaw('
                si.shipment_id,
                COALESCE(SUM(
                    si.quantity * oi.total_amount
                    / NULLIF(oi.quantity, 0)
                ), 0) AS expected_amount
            ')
            ->groupBy('si.shipment_id');

        $query = DB::table('shipments as sh')
            ->join('orders as o', 'o.id', '=', 'sh.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('sellers as s', 's.id', '=', 'sh.seller_id')
            ->leftJoin('users as seller_user', 'seller_user.id', '=', 's.user_id')
            ->leftJoin('seller_store_settings as store', 'store.seller_id', '=', 's.id')
            ->leftJoin('master_types as shipment_status', 'shipment_status.id', '=', 'sh.status_id')
            ->leftJoin('cod_remittances as cr', 'cr.shipment_id', '=', 'sh.id')
            ->leftJoin('master_types as remittance_status', 'remittance_status.id', '=', 'cr.status_id')
            ->leftJoinSub($expectedSubquery, 'expected', function ($join) {
                $join->on('expected.shipment_id', '=', 'sh.id');
            })
            ->where('o.payment_method_id', self::COD_PAYMENT_METHOD_ID)
            ->select([
                'sh.id as shipment_id',
                'sh.order_id',
                'sh.seller_id',
                'sh.carrier',
                'sh.tracking_number',
                'sh.tracking_url',
                'sh.status_id as shipment_status_id',
                'shipment_status.description as shipment_status',
                'sh.shipped_at',
                'sh.delivered_at',
                'o.order_number',
                'o.user_id as buyer_id',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                's.user_id as seller_user_id',
                'seller_user.name as seller_name',
                'store.store_name',
                'cr.id as remittance_id',
                'cr.status_id as remittance_status_id',
                'remittance_status.value as remittance_status',
                'cr.collected_amount',
                'cr.remitted_amount',
                'cr.currency',
                'cr.courier_reference',
                'cr.received_reference',
                'cr.collected_at',
                'cr.remitted_at',
                'cr.notes',
                'cr.created_at as remittance_created_at',
            ])
            ->selectRaw('
                COALESCE(cr.expected_amount, expected.expected_amount, 0)
                AS expected_amount
            ');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('o.order_number', 'ilike', "%{$search}%")
                    ->orWhere('sh.tracking_number', 'ilike', "%{$search}%")
                    ->orWhere('sh.carrier', 'ilike', "%{$search}%")
                    ->orWhere('buyer.name', 'ilike', "%{$search}%")
                    ->orWhere('buyer.email', 'ilike', "%{$search}%")
                    ->orWhere('seller_user.name', 'ilike', "%{$search}%")
                    ->orWhere('store.store_name', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $statusId = $this->statusId($filters['status']);
            $query->where('cr.status_id', $statusId);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('sh.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['carrier'])) {
            $query->where('sh.carrier', 'ilike', '%' . trim($filters['carrier']) . '%');
        }

        if (!empty($filters['from'])) {
            $query->whereDate('o.created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('o.created_at', '<=', $filters['to']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        $paginator = $query
            ->orderByDesc('sh.id')
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

    public function show(int $shipmentId): array
    {
        $shipment = DB::table('shipments as sh')
            ->join('orders as o', 'o.id', '=', 'sh.order_id')
            ->where('sh.id', $shipmentId)
            ->where('o.payment_method_id', self::COD_PAYMENT_METHOD_ID)
            ->select('sh.*', 'o.order_number', 'o.user_id', 'o.total_amount')
            ->first();

        if (!$shipment) {
            throw ValidationException::withMessages([
                'shipment_id' => ['COD shipment not found.'],
            ]);
        }

        $remittance = DB::table('cod_remittances as cr')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'cr.status_id')
            ->where('cr.shipment_id', $shipmentId)
            ->select('cr.*', 'mt.value as status', 'mt.description as status_description')
            ->first();

        $items = DB::table('shipment_items as si')
            ->join('order_items as oi', 'oi.id', '=', 'si.order_item_id')
            ->where('si.shipment_id', $shipmentId)
            ->select([
                'si.id as shipment_item_id',
                'oi.id as order_item_id',
                'oi.product_id',
                'oi.product_name',
                'oi.seller_id',
                'oi.sku',
                'oi.quantity as ordered_quantity',
                'si.quantity as shipped_quantity',
                'oi.unit_price',
                'oi.total_amount',
            ])
            ->get();

        return [
            'shipment' => $shipment,
            'remittance' => $remittance,
            'items' => $items,
            'calculated_expected_amount' => $this->expectedAmount($shipmentId),
        ];
    }

    public function updateRemittance(int $shipmentId, array $data): array
    {
        return DB::transaction(function () use ($shipmentId, $data) {
            $shipment = DB::table('shipments as sh')
                ->join('orders as o', 'o.id', '=', 'sh.order_id')
                ->where('sh.id', $shipmentId)
                ->where('o.payment_method_id', self::COD_PAYMENT_METHOD_ID)
                ->select('sh.id', 'sh.order_id', 'sh.seller_id')
                ->lockForUpdate()
                ->first();

            if (!$shipment) {
                throw ValidationException::withMessages([
                    'shipment_id' => ['COD shipment not found.'],
                ]);
            }

            $statusId = $this->statusId($data['status']);

            $existing = DB::table('cod_remittances')
                ->where('shipment_id', $shipmentId)
                ->lockForUpdate()
                ->first();

            $expected = $existing
                ? (float) $existing->expected_amount
                : $this->expectedAmount($shipmentId);

            $collected = array_key_exists('collected_amount', $data)
                ? (float) $data['collected_amount']
                : (float) ($existing->collected_amount ?? 0);

            $remitted = array_key_exists('remitted_amount', $data)
                ? (float) $data['remitted_amount']
                : (float) ($existing->remitted_amount ?? 0);

            if ($remitted > $collected) {
                throw ValidationException::withMessages([
                    'remitted_amount' => [
                        'Remitted amount cannot exceed collected amount.',
                    ],
                ]);
            }

            if ($data['status'] === 'received' && $remitted <= 0) {
                throw ValidationException::withMessages([
                    'remitted_amount' => [
                        'Enter the amount actually received from the courier.',
                    ],
                ]);
            }

            $now = now();

            $payload = [
                'order_id' => $shipment->order_id,
                'seller_id' => $shipment->seller_id,
                'status_id' => $statusId,
                'expected_amount' => $expected,
                'collected_amount' => $collected,
                'remitted_amount' => $remitted,
                'currency' => $data['currency'] ?? ($existing->currency ?? 'PKR'),
                'courier_reference' => $data['courier_reference']
                    ?? ($existing->courier_reference ?? null),
                'received_reference' => $data['received_reference']
                    ?? ($existing->received_reference ?? null),
                'collected_at' => $data['collected_at']
                    ?? ($existing->collected_at ?? null),
                'remitted_at' => $data['status'] === 'received'
                    ? ($data['remitted_at'] ?? $existing->remitted_at ?? $now)
                    : ($data['remitted_at'] ?? ($existing->remitted_at ?? null)),
                'notes' => array_key_exists('notes', $data)
                    ? $data['notes']
                    : ($existing->notes ?? null),
                'updated_at' => $now,
            ];

            if (!$existing) {
                $payload['shipment_id'] = $shipmentId;
                $payload['created_at'] = $now;

                $id = DB::table('cod_remittances')->insertGetId($payload);
            } else {
                DB::table('cod_remittances')
                    ->where('id', $existing->id)
                    ->update($payload);

                $id = $existing->id;
            }

            return [
                'message' => 'COD remittance updated successfully.',
                'remittance' => DB::table('cod_remittances as cr')
                    ->join('master_types as mt', 'mt.id', '=', 'cr.status_id')
                    ->where('cr.id', $id)
                    ->select('cr.*', 'mt.value as status', 'mt.description as status_description')
                    ->first(),
            ];
        });
    }
}
