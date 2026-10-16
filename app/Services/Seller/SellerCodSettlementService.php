<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerCodSettlementService
{
    private function getSellerId(): int
    {
        $seller = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->first(['id']);

        if (!$seller) {
            abort(404, 'Seller profile not found.');
        }

        return (int) $seller->id;
    }

    private function baseQuery(int $sellerId)
    {
        return DB::table('cod_remittances as cr')
            ->join('orders as o', 'o.id', '=', 'cr.order_id')
            ->leftJoin('shipments as sh', 'sh.id', '=', 'cr.shipment_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('master_types as settlement_status',
                'settlement_status.id', '=', 'cr.status_id')
            ->leftJoin('master_types as shipment_status',
                'shipment_status.id', '=', 'sh.status_id')
            ->where('cr.seller_id', $sellerId);
    }

    private function selectColumns()
    {
        return [
            'cr.id',
            'cr.shipment_id',
            'cr.order_id',
            'o.order_number',
            'buyer.name as buyer_name',
            'cr.expected_amount',
            'cr.collected_amount',
            'cr.remitted_amount',
            'cr.currency',
            'cr.courier_reference',
            'cr.received_reference',
            'cr.collected_at',
            'cr.remitted_at',
            'cr.notes',
            'cr.status_id',
            'settlement_status.description as settlement_status',
            'sh.tracking_number',
            'sh.carrier',
            'sh.status_id as shipment_status_id',
            'shipment_status.description as delivery_status',
            'cr.created_at',
            'cr.updated_at',
        ];
    }

    public function summary(): array
    {
        $sellerId = $this->getSellerId();

        $summary = DB::table('cod_remittances')
            ->where('seller_id', $sellerId)
            ->selectRaw('COUNT(*) as total_settlements')
            ->selectRaw('COALESCE(SUM(expected_amount), 0) as expected_amount')
            ->selectRaw('COALESCE(SUM(collected_amount), 0) as collected_amount')
            ->selectRaw('COALESCE(SUM(remitted_amount), 0) as remitted_amount')
            ->selectRaw(
                'COALESCE(SUM(GREATEST(collected_amount - remitted_amount, 0)), 0) as pending_amount'
            )
            ->first();

        return [
            'total_settlements' => (int) $summary->total_settlements,
            'expected_amount' => (float) $summary->expected_amount,
            'collected_amount' => (float) $summary->collected_amount,
            'remitted_amount' => (float) $summary->remitted_amount,
            'pending_amount' => (float) $summary->pending_amount,
            'currency' => 'PKR',
        ];
    }

    public function index(array $filters = []): array
    {
        $sellerId = $this->getSellerId();

        $query = $this->baseQuery($sellerId);

        if (!empty($filters['status_id'])) {
            $query->where('cr.status_id', $filters['status_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('o.order_number', 'ILIKE', "%{$search}%")
                    ->orWhere('buyer.name', 'ILIKE', "%{$search}%")
                    ->orWhere('sh.tracking_number', 'ILIKE', "%{$search}%")
                    ->orWhere('cr.courier_reference', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $paginator = $query->select($this->selectColumns())->orderByDesc('cr.id')->paginate($perPage);

        return [
            'items' => $paginator->items(),
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
        $sellerId = $this->getSellerId();

        $settlement = $this->baseQuery($sellerId)->where('cr.id', $id)->select($this->selectColumns())->first();

        if (!$settlement) {
            abort(404, 'COD settlement not found.');
        }

        return $settlement;
    }
}
