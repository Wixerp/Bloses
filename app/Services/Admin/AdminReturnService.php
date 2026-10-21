<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReturnService
{
    private function getStatus(string $type, string $value): object
    {
        $status = DB::table('master_types')
            ->where('key', $type)
            ->where('value', $value)
            ->first();

        if (!$status) {
            throw ValidationException::withMessages([
                'status' => ["Status '{$value}' was not found."],
            ]);
        }

        return $status;
    }

    private function baseQuery()
    {
        $itemSummary = DB::table('return_items')
            ->select(
                'return_id',
                DB::raw('COALESCE(SUM(total_amount), 0) as return_amount'),
                DB::raw('COALESCE(SUM(quantity), 0) as total_quantity'),
                DB::raw('COUNT(*) as items_count')
            )
            ->groupBy('return_id');

        return DB::table('returns as r')
            ->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('users as customer', 'customer.id', '=', 'r.user_id')
            ->leftJoin('sellers as s', 's.id', '=', 'r.seller_id')
            ->leftJoin('users as seller', 'seller.id', '=', 's.user_id')
            ->leftJoin('master_types as st', function ($join) {
                $join->on('st.id', '=', 'r.status_id')
                    ->where('st.key', '=', 'return_status');
            })
            ->leftJoinSub($itemSummary, 'ri', function ($join) {
                $join->on('ri.return_id', '=', 'r.id');
            })
            ->select([
                'r.id',
                'r.return_number',
                'r.order_id',
                'o.order_number',
                'o.status_id as order_status_id',
                'customer.name as customer_name',
                'customer.email as customer_email',
                'r.user_id as customer_id',
                'r.seller_id',
                'seller.name as seller_name',
                'r.status_id',
                'st.value as status_key',
                'st.description as status',
                'r.reason_id',
                'r.reason',
                'r.customer_note',
                'r.seller_note',
                'r.admin_note',
                'r.carrier',
                'r.tracking_number',
                'r.requested_at',
                'r.approved_at',
                'r.picked_up_at',
                'r.received_at',
                'r.rejected_at',
                'r.completed_at',
                'r.created_at',
                'r.updated_at',
                DB::raw('COALESCE(ri.return_amount, 0) as return_amount'),
                DB::raw('COALESCE(ri.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(ri.items_count, 0) as items_count'),
            ]);
    }

    public function getReturns(array $filters = [])
    {
        $query = $this->baseQuery();

        if (!empty($filters['return_id'])) {
            $query->where('r.id', $filters['return_id']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('r.order_id', $filters['order_id']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('r.user_id', $filters['customer_id']);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('r.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['status_id'])) {
            $query->where('r.status_id', $filters['status_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('r.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('r.created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('r.id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getReturnDetails(int $returnId): array
    {
        $return = $this->baseQuery()
            ->where('r.id', $returnId)
            ->first();

        if (!$return) {
            abort(404, 'Return request not found.');
        }

        $items = DB::table('return_items as ri')
            ->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_item_id')
            ->where('ri.return_id', $returnId)
            ->select([
                'ri.id as return_item_id',
                'ri.order_item_id',
                'oi.product_id',
                'oi.product_name',
                'oi.variant_name',
                'oi.sku',
                'ri.quantity',
                'ri.unit_price',
                'ri.total_amount',
                'ri.reason_id',
                'ri.customer_note',
                'ri.admin_note',
            ])
            ->orderBy('ri.id')
            ->get();

        $refunds = DB::table('refunds as rf')
            ->leftJoin('master_types as rs', function ($join) {
                $join->on('rs.id', '=', 'rf.status_id')
                    ->whereIn('rs.key', [
                        'refund_pending',
                        'refund_approved',
                        'refund_rejected',
                    ]);
            })
            ->where('rf.order_id', $return->order_id)
            ->where('rf.user_id', $return->customer_id)
            ->select([
                'rf.id',
                'rf.refund_number',
                'rf.order_id',
                'rf.order_item_id',
                'rf.status_id',
                'rs.description as status',
                'rf.requested_amount',
                'rf.approved_amount',
                'rf.refunded_amount',
                'rf.currency',
                'rf.transaction_id',
                'rf.gateway',
                'rf.customer_note',
                'rf.admin_note',
                'rf.requested_at',
                'rf.approved_at',
                'rf.refunded_at',
                'rf.rejected_at',
            ])
            ->orderByDesc('rf.id')
            ->get();

        return [
            'return' => $return,
            'items' => $items,
            'refunds' => $refunds,
        ];
    }

    public function updateStatus(
        int $returnId,
        string $statusValue,
        ?string $adminNote = null
    ): array {
        $allowedStatuses = [
            'return_approved',
            'return_rejected',
            'pickup_scheduled',
            'picked_up',
            'in_transit',
            'received',
            'inspection_quality_check',
            'return_accepted',
            'return_failed',
            'refund_initiated',
            'refunded',
            'return_cancelled',
        ];

        if (!in_array($statusValue, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid return status.'],
            ]);
        }

        $status = $this->getStatus('return_status', $statusValue);

        return DB::transaction(function () use (
            $returnId,
            $statusValue,
            $status,
            $adminNote
        ) {
            $return = DB::table('returns')
                ->where('id', $returnId)
                ->lockForUpdate()
                ->first();

            if (!$return) {
                abort(404, 'Return request not found.');
            }

            $currentStatus = DB::table('master_types')
                ->where('id', $return->status_id)
                ->value('value');

            if (in_array($currentStatus, [
                'return_rejected',
                'return_failed',
                'refunded',
                'return_cancelled',
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => [
                        'This return request is already in a final state.',
                    ],
                ]);
            }

            $updates = [
                'status_id' => $status->id,
                'updated_at' => now(),
            ];

            if ($adminNote !== null) {
                $updates['admin_note'] = trim($adminNote);
            }

            $timestampColumns = [
                'return_approved' => 'approved_at',
                'return_rejected' => 'rejected_at',
                'picked_up' => 'picked_up_at',
                'received' => 'received_at',
                'refunded' => 'completed_at',
            ];

            if (isset($timestampColumns[$statusValue])) {
                $updates[$timestampColumns[$statusValue]] = now();
            }

            DB::table('returns')
                ->where('id', $returnId)
                ->update($updates);

            return [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'order_id' => $return->order_id,
                'status_id' => (int) $status->id,
                'status' => $status->description,
                'admin_note' => $adminNote ?? $return->admin_note,
                'updated_at' => now()->toDateTimeString(),
            ];
        });
    }

    public function cancelOrder(int $orderId): array
    {
        $cancelledStatus = $this->getStatus(
            'order_status',
            'cancelled'
        );

        return DB::transaction(function () use (
            $orderId,
            $cancelledStatus
        ) {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Order not found.');
            }

            $currentStatus = DB::table('master_types')
                ->where('id', $order->status_id)
                ->where('key', 'order_status')
                ->value('value');

            if (in_array($currentStatus, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => [
                        'Delivered or already cancelled orders cannot be cancelled here.',
                    ],
                ]);
            }

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'status_id' => $cancelledStatus->id,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status_id' => (int) $cancelledStatus->id,
                'status' => $cancelledStatus->description,
                'cancelled_at' => now()->toDateTimeString(),
            ];
        });
    }
}
