<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerReportService
{
    private function orderAggregate(Request $request)
    {
        $query = DB::table('orders as o')
            ->selectRaw("
                o.user_id,
                COUNT(*) AS total_orders,
                COUNT(*) FILTER (WHERE o.status_id = 48) AS delivered_orders,
                COUNT(*) FILTER (WHERE o.status_id = 49) AS cancelled_orders,
                COUNT(*) FILTER (
                    WHERE o.status_id NOT IN (48, 49)
                ) AS other_orders,
                COALESCE(SUM(o.total_amount) FILTER (
                    WHERE o.status_id <> 49
                ), 0) AS total_spent,
                COALESCE(SUM(o.total_amount) FILTER (
                    WHERE o.status_id = 48
                ), 0) AS delivered_amount,
                MAX(COALESCE(o.placed_at, o.created_at)) AS last_order_at
            ")
            ->groupBy('o.user_id');

        if ($request->filled('date_from')) {
            $query->whereDate(
                DB::raw('COALESCE(o.placed_at, o.created_at)'),
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                DB::raw('COALESCE(o.placed_at, o.created_at)'),
                '<=',
                $request->date_to
            );
        }

        return $query;
    }

    private function customerQuery(Request $request)
    {
        $orders = $this->orderAggregate($request);

        return DB::table('users as u')
            ->leftJoinSub($orders, 'ord', function ($join) {
                $join->on('ord.user_id', '=', 'u.id');
            })
            ->selectRaw("
                u.id AS customer_id,
                u.name AS customer_name,
                u.email,
                u.phone,
                u.status_id,
                u.is_active,
                u.is_block,
                u.email_verified_at,
                u.last_login_at,
                u.created_at AS customer_since,
                COALESCE(ord.total_orders, 0) AS total_orders,
                COALESCE(ord.delivered_orders, 0) AS delivered_orders,
                COALESCE(ord.cancelled_orders, 0) AS cancelled_orders,
                COALESCE(ord.other_orders, 0) AS other_orders,
                COALESCE(ord.total_spent, 0) AS total_spent,
                COALESCE(ord.delivered_amount, 0) AS delivered_amount,
                ord.last_order_at
            ")
            ->when($request->filled('customer_id'), function ($query) use ($request) {
                $query->where('u.id', $request->integer('customer_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim($request->search) . '%';

                $query->where(function ($q) use ($search) {
                    $q->where('u.name', 'ILIKE', $search)
                        ->orWhere('u.email', 'ILIKE', $search)
                        ->orWhere('u.phone', 'ILIKE', $search);
                });
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where(
                    'u.is_active',
                    $request->boolean('is_active') ? 1 : 0
                );
            })
            ->when($request->filled('is_block'), function ($query) use ($request) {
                $query->where(
                    'u.is_block',
                    $request->boolean('is_block') ? 1 : 0
                );
            });
    }

    public function report(Request $request): array
    {
        $customers = $this->customerQuery($request);

        $summary = DB::query()
            ->fromSub(clone $customers, 'c')
            ->selectRaw("
                COUNT(*) AS total_customers,
                COUNT(*) FILTER (
                    WHERE c.total_orders > 0
                ) AS customers_with_orders,
                COUNT(*) FILTER (
                    WHERE c.total_orders = 0
                ) AS customers_without_orders,
                COUNT(*) FILTER (
                    WHERE c.is_active = 1
                ) AS active_customers,
                COUNT(*) FILTER (
                    WHERE c.is_block = 1
                ) AS blocked_customers,
                COALESCE(SUM(c.total_orders), 0) AS total_orders,
                COALESCE(SUM(c.delivered_orders), 0) AS delivered_orders,
                COALESCE(SUM(c.cancelled_orders), 0) AS cancelled_orders,
                COALESCE(SUM(c.other_orders), 0) AS other_orders,
                COALESCE(SUM(c.total_spent), 0) AS total_spent,
                COALESCE(SUM(c.delivered_amount), 0) AS delivered_amount
            ")
            ->first();

        $perPage = (int) $request->input('per_page', 15);

        $customerList = (clone $customers)
            ->orderByDesc('total_spent')
            ->orderBy('customer_id')
            ->paginate($perPage);

        $topCustomers = (clone $customers)
            ->where('total_orders', '>', 0)
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        return [
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'customer_id' => $request->input('customer_id'),
                'search' => $request->input('search'),
                'is_active' => $request->input('is_active'),
                'is_block' => $request->input('is_block'),
            ],
            'summary' => [
                'total_customers' => (int) $summary->total_customers,
                'customers_with_orders' => (int) $summary->customers_with_orders,
                'customers_without_orders' => (int) $summary->customers_without_orders,
                'active_customers' => (int) $summary->active_customers,
                'blocked_customers' => (int) $summary->blocked_customers,
                'total_orders' => (int) $summary->total_orders,
                'delivered_orders' => (int) $summary->delivered_orders,
                'cancelled_orders' => (int) $summary->cancelled_orders,
                'other_orders' => (int) $summary->other_orders,
                'total_spent' => (float) $summary->total_spent,
                'delivered_amount' => (float) $summary->delivered_amount,
            ],
            'top_customers' => $topCustomers,
            'customers' => $customerList,
        ];
    }
}
