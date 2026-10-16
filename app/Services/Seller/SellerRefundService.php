<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerRefundService
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
        return DB::table('refunds as r')
            ->join('order_items as oi', 'oi.id', '=', 'r.order_item_id')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'r.user_id')
            ->leftJoin('master_types as refund_status',
                'refund_status.id', '=', 'r.status_id')
            ->leftJoin('master_types as refund_reason',
                'refund_reason.id', '=', 'r.reason_id')
            ->where('oi.seller_id', $sellerId);
    }

    private function columns(): array
    {
        return [
            'r.id',
            'r.refund_number',
            'r.order_id',
            'o.order_number',
            'r.order_item_id',
            'oi.product_name',
            'oi.variant_name',
            'oi.sku',
            'buyer.name as buyer_name',
            'r.requested_amount',
            'r.approved_amount',
            'r.refunded_amount',
            'r.currency',
            'refund_status.description as refund_status',
            'refund_reason.description as refund_reason',
            'r.customer_note',
            'r.transaction_id',
            'r.gateway',
            'r.requested_at',
            'r.approved_at',
            'r.refunded_at',
            'r.rejected_at',
            'r.created_at',
            'r.updated_at',
        ];
    }

    public function summary(): array
    {
        $sellerId = $this->getSellerId();

        $totals = DB::table('refunds as r')
            ->join('order_items as oi', 'oi.id', '=', 'r.order_item_id')
            ->where('oi.seller_id', $sellerId)
            ->select('r.currency')
            ->selectRaw('COUNT(*) as total_refunds')
            ->selectRaw('COALESCE(SUM(r.requested_amount), 0) as requested_amount')
            ->selectRaw('COALESCE(SUM(r.approved_amount), 0) as approved_amount')
            ->selectRaw('COALESCE(SUM(r.refunded_amount), 0) as refunded_amount')
            ->groupBy('r.currency')
            ->orderBy('r.currency')
            ->get();

        $statuses = DB::table('refunds as r')
            ->join('order_items as oi', 'oi.id', '=', 'r.order_item_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'r.status_id')
            ->where('oi.seller_id', $sellerId)
            ->select('r.status_id', 'mt.description as status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('r.status_id', 'mt.description')
            ->orderBy('r.status_id')
            ->get();

        return [
            'total_refunds' => (int) $totals->sum('total_refunds'),
            'currency_totals' => $totals,
            'statuses' => $statuses,
        ];
    }

    public function index(array $filters = []): array
    {
        $sellerId = $this->getSellerId();

        $query = $this->baseQuery($sellerId);

        if (isset($filters['status_id'])) {
            $query->where('r.status_id', $filters['status_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('r.refund_number', 'ILIKE', "%{$search}%")
                    ->orWhere('o.order_number', 'ILIKE', "%{$search}%")
                    ->orWhere('oi.product_name', 'ILIKE', "%{$search}%")
                    ->orWhere('buyer.name', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 10), 1),
            100
        );

        $paginator = $query
            ->select($this->columns())
            ->orderByDesc('r.id')
            ->paginate($perPage);

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

        $refund = $this->baseQuery($sellerId)
            ->where('r.id', $id)
            ->select($this->columns())
            ->first();

        if (!$refund) {
            abort(404, 'Refund not found.');
        }

        return $refund;
    }
}
