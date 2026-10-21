<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionPayoutReportService
{
    private function applyDateFilter($query, Request $request, string $column): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->date_to);
        }
    }

    private function commissionQuery(Request $request)
    {
        $query = DB::table('commission_records as cr')
            ->leftJoin('orders as o', 'o.id', '=', 'cr.order_id')
            ->leftJoin('seller_store_settings as store', 'store.seller_id', '=', 'cr.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 'cr.seller_id')
            ->select([
                'cr.id',
                'cr.order_id',
                'cr.seller_id',
                'cr.commission_setting_id',
                'cr.sale_amount',
                'cr.commission_type',
                'cr.commission_rate',
                'cr.commission_amount',
                'cr.seller_amount',
                'cr.status',
                'cr.created_at',
                'o.order_number',
                'store.store_name',
                'u.name as seller_name',
                'u.email as seller_email',
            ]);

        $this->applyDateFilter($query, $request, 'cr.created_at');

        if ($request->filled('seller_id')) {
            $query->where('cr.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('commission_status')) {
            $query->where('cr.status', $request->commission_status);
        }

        if ($request->filled('commission_type')) {
            $query->where('cr.commission_type', $request->commission_type);
        }

        if ($request->filled('order_id')) {
            $query->where('cr.order_id', $request->integer('order_id'));
        }

        return $query;
    }

    private function payoutQuery(Request $request)
    {
        $query = DB::table('seller_payouts as sp')
            ->leftJoin('seller_store_settings as store', 'store.seller_id', '=', 'sp.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 'sp.seller_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'sp.status_id')
            ->select([
                'sp.id',
                'sp.seller_id',
                'sp.seller_bank_account_id',
                'sp.status_id',
                'sp.gross_amount',
                'sp.commission_amount',
                'sp.deduction_amount',
                'sp.net_amount',
                'sp.currency',
                'sp.payout_number',
                'sp.transaction_id',
                'sp.payment_gateway',
                'sp.failure_reason',
                'sp.notes',
                'sp.processed_at',
                'sp.paid_at',
                'sp.failed_at',
                'sp.created_at',
                'store.store_name',
                'u.name as seller_name',
                'u.email as seller_email',
                DB::raw("COALESCE(st.value, st.description, 'Unknown') as payout_status"),
            ]);

        $this->applyDateFilter($query, $request, 'sp.created_at');

        if ($request->filled('seller_id')) {
            $query->where('sp.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('payout_status_id')) {
            $query->where('sp.status_id', $request->integer('payout_status_id'));
        }

        if ($request->filled('payout_number')) {
            $query->where('sp.payout_number', 'ILIKE', '%' . $request->payout_number . '%');
        }

        return $query;
    }

    private function filteredCommissionTotals(Request $request): object
    {
        $query = DB::table('commission_records as cr');

        $this->applyDateFilter($query, $request, 'cr.created_at');

        if ($request->filled('seller_id')) {
            $query->where('cr.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('commission_status')) {
            $query->where('cr.status', $request->commission_status);
        }

        if ($request->filled('commission_type')) {
            $query->where('cr.commission_type', $request->commission_type);
        }

        if ($request->filled('order_id')) {
            $query->where('cr.order_id', $request->integer('order_id'));
        }

        return $query->selectRaw("
            COUNT(*) AS total_commission_records,
            COUNT(DISTINCT cr.order_id) AS commission_orders,
            COALESCE(SUM(cr.sale_amount), 0) AS total_sale_amount,
            COALESCE(SUM(cr.commission_amount), 0) AS total_commission_amount,
            COALESCE(SUM(cr.seller_amount), 0) AS total_seller_amount
        ")->first();
    }

    private function filteredPayoutTotals(Request $request): object
    {
        $query = DB::table('seller_payouts as sp');

        $this->applyDateFilter($query, $request, 'sp.created_at');

        if ($request->filled('seller_id')) {
            $query->where('sp.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('payout_status_id')) {
            $query->where('sp.status_id', $request->integer('payout_status_id'));
        }

        if ($request->filled('payout_number')) {
            $query->where('sp.payout_number', 'ILIKE', '%' . $request->payout_number . '%');
        }

        return $query->selectRaw("
            COUNT(*) AS total_payouts,
            COUNT(*) FILTER (WHERE sp.paid_at IS NOT NULL) AS paid_payouts,
            COUNT(*) FILTER (
                WHERE sp.paid_at IS NULL
                  AND sp.failed_at IS NULL
            ) AS unpaid_payouts,
            COUNT(*) FILTER (WHERE sp.failed_at IS NOT NULL) AS failed_payouts,
            COALESCE(SUM(sp.gross_amount), 0) AS payout_gross_amount,
            COALESCE(SUM(sp.commission_amount), 0) AS payout_commission_amount,
            COALESCE(SUM(sp.deduction_amount), 0) AS payout_deduction_amount,
            COALESCE(SUM(sp.net_amount), 0) AS payout_net_amount,
            COALESCE(SUM(sp.net_amount) FILTER (WHERE sp.paid_at IS NOT NULL), 0)
                AS paid_net_amount,
            COALESCE(SUM(sp.net_amount) FILTER (
                WHERE sp.paid_at IS NULL AND sp.failed_at IS NULL
            ), 0) AS unpaid_net_amount,
            COALESCE(SUM(sp.net_amount) FILTER (WHERE sp.failed_at IS NOT NULL), 0)
                AS failed_net_amount
        ")->first();
    }

    private function commissionBySeller(Request $request)
    {
        $query = DB::table('commission_records as cr')
            ->leftJoin('seller_store_settings as store', 'store.seller_id', '=', 'cr.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 'cr.seller_id');

        $this->applyDateFilter($query, $request, 'cr.created_at');

        if ($request->filled('seller_id')) {
            $query->where('cr.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('commission_status')) {
            $query->where('cr.status', $request->commission_status);
        }

        if ($request->filled('commission_type')) {
            $query->where('cr.commission_type', $request->commission_type);
        }

        if ($request->filled('order_id')) {
            $query->where('cr.order_id', $request->integer('order_id'));
        }

        return $query->groupBy('cr.seller_id', 'store.store_name', 'u.name', 'u.email')
            ->selectRaw("
                cr.seller_id,
                COALESCE(store.store_name, 'N/A') AS store_name,
                COALESCE(u.name, 'N/A') AS seller_name,
                u.email AS seller_email,
                COUNT(*) AS commission_records,
                COUNT(DISTINCT cr.order_id) AS total_orders,
                COALESCE(SUM(cr.sale_amount), 0) AS total_sales,
                COALESCE(SUM(cr.commission_amount), 0) AS total_commission,
                COALESCE(SUM(cr.seller_amount), 0) AS total_seller_amount
            ")
            ->orderByDesc('total_commission')
            ->get();
    }

    private function payoutBySeller(Request $request)
    {
        $query = DB::table('seller_payouts as sp')
            ->leftJoin('seller_store_settings as store', 'store.seller_id', '=', 'sp.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 'sp.seller_id');

        $this->applyDateFilter($query, $request, 'sp.created_at');

        if ($request->filled('seller_id')) {
            $query->where('sp.seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('payout_status_id')) {
            $query->where('sp.status_id', $request->integer('payout_status_id'));
        }

        if ($request->filled('payout_number')) {
            $query->where('sp.payout_number', 'ILIKE', '%' . $request->payout_number . '%');
        }

        return $query->groupBy('sp.seller_id', 'store.store_name', 'u.name', 'u.email')
            ->selectRaw("
                sp.seller_id,
                COALESCE(store.store_name, 'N/A') AS store_name,
                COALESCE(u.name, 'N/A') AS seller_name,
                u.email AS seller_email,
                COUNT(*) AS total_payouts,
                COUNT(*) FILTER (WHERE sp.paid_at IS NOT NULL) AS paid_payouts,
                COUNT(*) FILTER (
                    WHERE sp.paid_at IS NULL AND sp.failed_at IS NULL
                ) AS unpaid_payouts,
                COUNT(*) FILTER (WHERE sp.failed_at IS NOT NULL) AS failed_payouts,
                COALESCE(SUM(sp.net_amount), 0) AS total_net_amount,
                COALESCE(SUM(sp.net_amount) FILTER (WHERE sp.paid_at IS NOT NULL), 0)
                    AS paid_amount,
                COALESCE(SUM(sp.net_amount) FILTER (
                    WHERE sp.paid_at IS NULL AND sp.failed_at IS NULL
                ), 0) AS unpaid_amount
            ")
            ->orderByDesc('total_net_amount')
            ->get();
    }

    public function report(Request $request): array
    {
        $commissionTotals = $this->filteredCommissionTotals($request);
        $payoutTotals = $this->filteredPayoutTotals($request);
        $perPage = (int) $request->input('per_page', 15);

        return [
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'seller_id' => $request->input('seller_id'),
                'commission_status' => $request->input('commission_status'),
                'commission_type' => $request->input('commission_type'),
                'payout_status_id' => $request->input('payout_status_id'),
                'order_id' => $request->input('order_id'),
                'payout_number' => $request->input('payout_number'),
            ],
            'summary' => [
                'commission' => [
                    'total_records' => (int) $commissionTotals->total_commission_records,
                    'total_orders' => (int) $commissionTotals->commission_orders,
                    'total_sales' => (float) $commissionTotals->total_sale_amount,
                    'total_commission' => (float) $commissionTotals->total_commission_amount,
                    'total_seller_amount' => (float) $commissionTotals->total_seller_amount,
                ],
                'payout' => [
                    'total_payouts' => (int) $payoutTotals->total_payouts,
                    'paid_payouts' => (int) $payoutTotals->paid_payouts,
                    'unpaid_payouts' => (int) $payoutTotals->unpaid_payouts,
                    'failed_payouts' => (int) $payoutTotals->failed_payouts,
                    'gross_amount' => (float) $payoutTotals->payout_gross_amount,
                    'commission_amount' => (float) $payoutTotals->payout_commission_amount,
                    'deduction_amount' => (float) $payoutTotals->payout_deduction_amount,
                    'net_amount' => (float) $payoutTotals->payout_net_amount,
                    'paid_amount' => (float) $payoutTotals->paid_net_amount,
                    'unpaid_amount' => (float) $payoutTotals->unpaid_net_amount,
                    'failed_amount' => (float) $payoutTotals->failed_net_amount,
                ],
            ],
            'commission_by_seller' => $this->commissionBySeller($request),
            'payout_by_seller' => $this->payoutBySeller($request),
            'commissions' => $this->commissionQuery($request)
                ->orderByDesc('cr.created_at')
                ->paginate($perPage),
            'payouts' => $this->payoutQuery($request)
                ->orderByDesc('sp.created_at')
                ->paginate($perPage),
        ];
    }
}
