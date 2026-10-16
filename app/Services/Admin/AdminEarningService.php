<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class AdminEarningService
{
    private function getEarningStatusId(string $status): ?int
    {
        $id = DB::table('master_types')
            ->where('key', 'earning_status')
            ->where('value', $status)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function summary(): array
    {
        $totals = DB::table('seller_earnings')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COUNT(DISTINCT seller_id) as total_sellers')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as total_commission')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as total_net_earnings')
            ->first();

        $availableId = $this->getEarningStatusId('available');
        $paidId = $this->getEarningStatusId('paid');

        $statusTotals = DB::table('seller_earnings')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_id = ? THEN net_amount ELSE 0 END), 0) as available_earnings',
                [$availableId ?? 0]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_id = ? THEN net_amount ELSE 0 END), 0) as paid_earnings',
                [$paidId ?? 0]
            )
            ->first();

        return [
            'total_records' => (int) $totals->total_records,
            'total_sellers' => (int) $totals->total_sellers,
            'total_sales' => (float) $totals->total_sales,
            'total_commission' => (float) $totals->total_commission,
            'total_net_earnings' => (float) $totals->total_net_earnings,
            'available_earnings' => (float) $statusTotals->available_earnings,
            'paid_earnings' => (float) $statusTotals->paid_earnings,
        ];
    }

    public function listing(array $filters = [])
    {
        $query = DB::table('seller_earnings')
            ->join('sellers', 'sellers.id', '=', 'seller_earnings.seller_id')
            ->join('users', 'users.id', '=', 'sellers.user_id')
            ->join('orders', 'orders.id', '=', 'seller_earnings.order_id')
            ->leftJoin(
                'master_types as earning_status',
                'earning_status.id',
                '=',
                'seller_earnings.status_id'
            )
            ->whereNotNull('seller_earnings.id')
            ->select([
                'seller_earnings.id',
                'seller_earnings.order_id',
                'orders.order_number',
                'seller_earnings.seller_id',
                'users.name as seller_name',
                'users.email as seller_email',
                'seller_earnings.gross_amount',
                'seller_earnings.commission_percentage',
                'seller_earnings.commission_amount',
                'seller_earnings.net_amount',
                'seller_earnings.status_id',
                'earning_status.value as status',
                'earning_status.description as status_name',
                'seller_earnings.available_at',
                'seller_earnings.created_at',
            ]);

        if (!empty($filters['seller_id'])) {
            $query->where(
                'seller_earnings.seller_id',
                $filters['seller_id']
            );
        }

        if (!empty($filters['status'])) {
            $statusId = $this->getEarningStatusId($filters['status']);

            if (!$statusId) {
                return [];
            }

            $query->where('seller_earnings.status_id', $statusId);
        }

        return $query->orderByDesc('seller_earnings.id')->get();
    }
}
