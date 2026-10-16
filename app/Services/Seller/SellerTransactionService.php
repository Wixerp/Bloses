<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerTransactionService
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
        return DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->join('seller_earnings as se', function ($join) use ($sellerId) {
                $join->on('se.order_id', '=', 'p.order_id')
                    ->where('se.seller_id', '=', $sellerId);
            })
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin('master_types as payment_status',
                'payment_status.id', '=', 'p.status_id')
            ->leftJoin('master_types as payment_method',
                'payment_method.id', '=', 'p.payment_method_id');
    }

    private function columns(): array
    {
        return [
            'p.id',
            'p.order_id',
            'o.order_number',
            'buyer.name as buyer_name',
            'p.transaction_id',
            'p.gateway',
            'p.gateway_reference',
            'payment_method.description as payment_method',
            'payment_status.description as payment_status',
            'se.gross_amount as seller_gross_amount',
            'se.commission_amount as seller_commission',
            'se.net_amount as seller_net_amount',
            'se.commission_percentage',
            'se.currency',
            'p.paid_at',
            'p.failed_at',
            'p.refunded_at',
            'p.failure_reason',
            'p.created_at',
            'p.updated_at',
        ];
    }


    public function summary(): array
    {
        $sellerId = $this->getSellerId();

        $transactionStats = DB::table('payments as p')
            ->join('seller_earnings as se', function ($join) use ($sellerId) {
                $join->on('se.order_id', '=', 'p.order_id')
                    ->where('se.seller_id', '=', $sellerId);
            })
            ->whereRaw('p.id = (
                SELECT MIN(p2.id)
                FROM payments p2
                WHERE p2.order_id = p.order_id
            )')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COUNT(*) FILTER (WHERE p.paid_at IS NOT NULL) as successful_transactions')
            ->selectRaw('COUNT(*) FILTER (WHERE p.failed_at IS NOT NULL) as failed_transactions')
            ->selectRaw('COUNT(*) FILTER (WHERE p.refunded_at IS NOT NULL) as refunded_transactions')
            ->first();

        $netAmount = DB::table('seller_earnings as se')
            ->where('se.seller_id', $sellerId)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payments as p')
                    ->whereColumn('p.order_id', 'se.order_id');
            })
            ->sum('se.net_amount');

        return [
            'total_transactions' => (int) $transactionStats->total_transactions,
            'successful_transactions' => (int) $transactionStats->successful_transactions,
            'failed_transactions' => (int) $transactionStats->failed_transactions,
            'refunded_transactions' => (int) $transactionStats->refunded_transactions,
            'seller_net_amount' => (float) $netAmount,
            'currency' => 'PKR',
        ];
    }


    public function index(array $filters = []): array
    {
        $sellerId = $this->getSellerId();

        $query = $this->baseQuery($sellerId);

        if (!empty($filters['status_id'])) {
            $query->where('p.status_id', $filters['status_id']);
        }

        if (!empty($filters['payment_method_id'])) {
            $query->where('p.payment_method_id', $filters['payment_method_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('o.order_number', 'ILIKE', "%{$search}%")
                    ->orWhere('p.transaction_id', 'ILIKE', "%{$search}%")
                    ->orWhere('p.gateway_reference', 'ILIKE', "%{$search}%")
                    ->orWhere('p.gateway', 'ILIKE', "%{$search}%")
                    ->orWhere('buyer.name', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 10), 1),
            100
        );

        $paginator = $query
            ->select($this->columns())
            ->orderByDesc('p.id')
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
}
