<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRefundService
{
    public function index(array $filters = []): array
    {
        $query = DB::table('refunds as r')
            ->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('payments as p', 'p.id', '=', 'r.payment_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'r.status_id')
            ->leftJoin('master_types as rt', 'rt.id', '=', 'r.reason_id')
            ->select(
                'r.id',
                'r.refund_number',
                'r.order_id',
                'o.order_number',
                'r.order_item_id',
                'r.payment_id',
                'r.user_id',
                'u.name as buyer_name',
                'u.email as buyer_email',
                'r.status_id',
                'st.description as status_name',
                'r.reason_id',
                'rt.description as reason_name',
                'r.requested_amount',
                'r.approved_amount',
                'r.refunded_amount',
                'r.currency',
                'r.transaction_id',
                'r.gateway',
                'r.customer_note',
                'r.admin_note',
                'r.requested_at',
                'r.approved_at',
                'r.refunded_at',
                'r.rejected_at',
                'r.created_at'
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('r.refund_number', 'ilike', "%{$search}%")
                    ->orWhere('o.order_number', 'ilike', "%{$search}%")
                    ->orWhere('u.name', 'ilike', "%{$search}%")
                    ->orWhere('u.email', 'ilike', "%{$search}%")
                    ->orWhere('r.transaction_id', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['status_id'])) {
            $query->where('r.status_id', $filters['status_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('r.user_id', $filters['user_id']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('r.created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('r.created_at', '<=', $filters['to']);
        }

        $paginator = $query
            ->orderByDesc('r.id')
            ->paginate($filters['per_page'] ?? 15);

        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function show(int $id): object
    {
        $refund = DB::table('refunds as r')
            ->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('payments as p', 'p.id', '=', 'r.payment_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'r.status_id')
            ->leftJoin('master_types as rt', 'rt.id', '=', 'r.reason_id')
            ->where('r.id', $id)
            ->select(
                'r.*',
                'o.order_number',
                'u.name as buyer_name',
                'u.email as buyer_email',
                'st.description as status_name',
                'rt.description as reason_name',
                'p.transaction_id as payment_transaction_id',
                'p.gateway as payment_gateway'
            )
            ->first();

        if (!$refund) {
            abort(404, 'Refund record nahi mila.');
        }

        return $refund;
    }

    public function approve(
        int $id,
        int $statusId,
        float $approvedAmount,
        ?string $adminNote = null
    ): object {
        return DB::transaction(function () use (
            $id, $statusId, $approvedAmount, $adminNote
        ) {
            $refund = DB::table('refunds')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$refund) {
                abort(404, 'Refund record nahi mila.');
            }

            if ($refund->rejected_at || $refund->refunded_at) {
                throw ValidationException::withMessages([
                    'refund' => [
                        'Rejected ya completed refund approve nahi ho sakta.',
                    ],
                ]);
            }

            if ($approvedAmount <= 0 ||
                $approvedAmount > (float) $refund->requested_amount) {
                throw ValidationException::withMessages([
                    'approved_amount' => [
                        'Approved amount zero se zyada aur requested amount se kam ya barabar honi chahiye.',
                    ],
                ]);
            }

            DB::table('refunds')
                ->where('id', $id)
                ->update([
                    'status_id' => $statusId,
                    'approved_amount' => $approvedAmount,
                    'approved_at' => now(),
                    'admin_note' => $adminNote ?? $refund->admin_note,
                    'updated_at' => now(),
                ]);

            return $this->show($id);
        });
    }

    public function reject(
        int $id,
        int $statusId,
        string $adminNote
    ): object {
        return DB::transaction(function () use ($id, $statusId, $adminNote) {
            $refund = DB::table('refunds')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$refund) {
                abort(404, 'Refund record nahi mila.');
            }

            if ($refund->refunded_at) {
                throw ValidationException::withMessages([
                    'refund' => ['Completed refund reject nahi ho sakta.'],
                ]);
            }

            DB::table('refunds')
                ->where('id', $id)
                ->update([
                    'status_id' => $statusId,
                    'rejected_at' => now(),
                    'admin_note' => $adminNote,
                    'updated_at' => now(),
                ]);

            return $this->show($id);
        });
    }

    public function markRefunded(
        int $id,
        int $statusId,
        float $refundedAmount,
        ?string $transactionId = null,
        ?string $gateway = null,
        ?string $gatewayResponse = null
    ): object {
        return DB::transaction(function () use (
            $id, $statusId, $refundedAmount, $transactionId,
            $gateway, $gatewayResponse
        ) {
            $refund = DB::table('refunds')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$refund) {
                abort(404, 'Refund record nahi mila.');
            }

            if ($refund->rejected_at || $refund->refunded_at) {
                throw ValidationException::withMessages([
                    'refund' => [
                        'Ye refund pehle hi reject ya complete ho chuka hai.',
                    ],
                ]);
            }

            if ((float) $refund->approved_amount <= 0 ||
                $refundedAmount <= 0 ||
                $refundedAmount > (float) $refund->approved_amount) {
                throw ValidationException::withMessages([
                    'refunded_amount' => [
                        'Refunded amount approved amount se zyada nahi ho sakti.',
                    ],
                ]);
            }

            DB::table('refunds')
                ->where('id', $id)
                ->update([
                    'status_id' => $statusId,
                    'refunded_amount' => $refundedAmount,
                    'transaction_id' => $transactionId,
                    'gateway' => $gateway ?? $refund->gateway,
                    'gateway_response' => $gatewayResponse,
                    'refunded_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->show($id);
        });
    }

    public function summary(): array
    {
        $result = DB::table('refunds')
            ->selectRaw('COUNT(*) AS total_refunds')
            ->selectRaw('COALESCE(SUM(requested_amount), 0) AS requested_amount')
            ->selectRaw('COALESCE(SUM(approved_amount), 0) AS approved_amount')
            ->selectRaw('COALESCE(SUM(refunded_amount), 0) AS refunded_amount')
            ->selectRaw("COUNT(*) FILTER (WHERE refunded_at IS NOT NULL) AS completed_refunds")
            ->selectRaw("COUNT(*) FILTER (WHERE rejected_at IS NOT NULL) AS rejected_refunds")
            ->first();

        return (array) $result;
    }
}
