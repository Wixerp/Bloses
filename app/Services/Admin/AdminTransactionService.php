<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTransactionService
{
    public function index(array $filters)
    {
        $query = DB::table('orders as o')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'o.user_id')
            ->leftJoin(
                'master_types as method',
                'method.id',
                '=',
                'o.payment_method_id'
            )
            ->leftJoin(
                'master_types as order_status',
                'order_status.id',
                '=',
                'o.status_id'
            )
            ->leftJoin('payments as p', 'p.order_id', '=', 'o.id')
            ->leftJoin(
                'master_types as payment_status',
                'payment_status.id',
                '=',
                'p.status_id'
            )
            ->select([
                'o.id as order_id',
                'o.order_number',
                'o.user_id as buyer_id',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                'o.payment_method_id',
                'method.description as payment_method',
                'o.total_amount as order_amount',
                'o.created_at as order_date',
                'order_status.description as order_status',
                'p.id as payment_id',
                'p.amount as payment_amount',
                'p.currency',
                'p.transaction_id',
                'p.gateway',
                'p.gateway_reference',
                'p.paid_at',
                'p.failed_at',
                'payment_status.value as payment_status',
                'payment_status.description as payment_status_name',
            ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('o.order_number', 'ilike', "%{$search}%")
                    ->orWhere('p.transaction_id', 'ilike', "%{$search}%")
                    ->orWhere('p.gateway_reference', 'ilike', "%{$search}%")
                    ->orWhere('buyer.name', 'ilike', "%{$search}%")
                    ->orWhere('buyer.email', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['payment_method_id'])) {
            $query->where(
                'o.payment_method_id',
                $filters['payment_method_id']
            );
        }

        if (!empty($filters['from'])) {
            $query->whereDate('o.created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('o.created_at', '<=', $filters['to']);
        }

        if (!empty($filters['status'])) {
            if (strtolower($filters['status']) === 'no_payment_record') {
                $query->whereNull('p.id');
            } else {
                $query->where(
                    'payment_status.value',
                    $filters['status']
                );
            }
        }

        $transactions = $query
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.id')
            ->paginate($filters['per_page'] ?? 15);

        $transactions->getCollection()->transform(function ($row) {
            $row->record_type = $row->payment_id
                ? 'payment'
                : 'order_without_payment';

            $row->display_status = $row->payment_id
                ? ($row->payment_status_name
                    ?? $row->payment_status
                    ?? 'Unknown')
                : 'No payment record';

            return $row;
        });

        return $transactions;
    }

    public function summary(): array
    {
        $orders = DB::table('orders')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw(
                'COALESCE(SUM(total_amount), 0) as total_order_amount'
            )
            ->first();

        $payments = DB::table('payments')
            ->selectRaw('COUNT(*) as total_payment_records')
            ->selectRaw(
                'COALESCE(SUM(amount), 0) as total_payment_amount'
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN paid_at IS NOT NULL THEN amount ELSE 0 END), 0) as paid_amount'
            )
            ->first();

        return [
            'total_orders' => (int) $orders->total_orders,
            'total_order_amount' => $orders->total_order_amount,
            'total_payment_records' => (int) $payments->total_payment_records,
            'total_payment_amount' => $payments->total_payment_amount,
            'paid_amount' => $payments->paid_amount,
        ];
    }
}
