<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerCommissionService
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
        return DB::table('seller_earnings as se')
            ->join('orders as o', 'o.id', '=', 'se.order_id')
            ->leftJoin(
                'master_types as mt',
                'mt.id',
                '=',
                'se.status_id'
            )
            ->where('se.seller_id', $sellerId);
    }

    public function summary(): array
    {
        $sellerId = $this->getSellerId();

        $summary = DB::table('seller_earnings as se')
            ->where('se.seller_id', $sellerId)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(se.gross_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(se.commission_amount), 0) as total_commission')
            ->selectRaw('COALESCE(SUM(se.net_amount), 0) as total_net_earnings')
            ->selectRaw("
                COALESCE(
                    SUM(se.net_amount) FILTER (
                        WHERE LOWER(COALESCE(
                            (SELECT mt.description
                             FROM master_types mt
                             WHERE mt.id = se.status_id),
                            ''
                        )) = 'available'
                    ),
                    0
                ) as available_earnings
            ")
            ->selectRaw("
                COALESCE(
                    SUM(se.net_amount) FILTER (
                        WHERE LOWER(COALESCE(
                            (SELECT mt.description
                             FROM master_types mt
                             WHERE mt.id = se.status_id),
                            ''
                        )) = 'paid'
                    ),
                    0
                ) as paid_earnings
            ")
            ->first();

        return [
            'total_orders' => (int) $summary->total_orders,
            'total_sales' => (float) $summary->total_sales,
            'total_commission' => (float) $summary->total_commission,
            'total_net_earnings' => (float) $summary->total_net_earnings,
            'available_earnings' => (float) $summary->available_earnings,
            'paid_earnings' => (float) $summary->paid_earnings,
            'commission_percentage' => 10,
            'currency' => 'PKR',
        ];
    }

    public function index(array $filters = []): array
    {
        $sellerId = $this->getSellerId();

        $query = $this->baseQuery($sellerId);

        if (!empty($filters['status_id'])) {
            $query->where('se.status_id', $filters['status_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(
                'o.order_number',
                'ILIKE',
                "%{$search}%"
            );
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 10), 1),
            100
        );

        $paginator = $query
            ->select([
                'se.id',
                'se.order_id',
                'o.order_number',
                'se.gross_amount',
                'se.commission_percentage',
                'se.commission_amount',
                'se.net_amount',
                'se.status_id',
                'mt.description as earning_status',
                'se.available_at',
                'se.created_at',
                'se.updated_at',
            ])
            ->orderByDesc('se.id')
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

        $commission = $this->baseQuery($sellerId)
            ->where('se.id', $id)
            ->select([
                'se.id',
                'se.order_id',
                'o.order_number',
                'se.gross_amount',
                'se.commission_percentage',
                'se.commission_amount',
                'se.net_amount',
                'se.status_id',
                'mt.description as earning_status',
                'se.available_at',
                'se.created_at',
                'se.updated_at',
            ])
            ->first();

        if (!$commission) {
            abort(404, 'Commission details not found.');
        }

        return $commission;
    }
}
