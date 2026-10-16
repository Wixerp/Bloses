<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPayoutService
{
    private function statusId(string $value): int
    {
        $id = DB::table('master_types')->where('key', 'payout_status')->where('value', $value)->value('id');

        if (!$id) {
            throw new \RuntimeException(
                "Payout status '{$value}' not found."
            );
        }

        return (int) $id;
    }


    public function listing(array $filters = [])
    {
        $query = DB::table('seller_payouts as p')
            ->leftJoin('sellers as s', 's.id', '=', 'p.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'p.status_id')
            ->leftJoin('seller_bank_accounts as payout_bank', 'payout_bank.id', '=', 'p.seller_bank_account_id')
            ->select([
                'p.id','p.seller_id','u.name as seller_name','u.email as seller_email','p.payout_number','p.gross_amount',
                'p.commission_amount','p.deduction_amount','p.net_amount','p.currency','p.status_id','st.value as status',
                'st.description as status_name','p.transaction_id','p.payment_gateway','p.failure_reason','p.notes',
                'p.processed_at','p.paid_at','p.failed_at','p.created_at','payout_bank.id as payout_bank_account_id',
                'payout_bank.account_holder_name as payout_account_holder_name','payout_bank.bank_name as bank_name',
                'payout_bank.account_number as payout_account_number','payout_bank.iban as payout_iban',
            ])
            ->selectSub(function ($sub) {
                $sub->from('seller_bank_accounts as b')
                    ->selectRaw("COALESCE(json_agg(json_build_object('id', b.id,
                                    'account_holder_name', b.account_holder_name,
                                    'bank_name', b.bank_name,
                                    'account_number', b.account_number,
                                    'iban', b.iban,
                                    'branch_name', b.branch_name,
                                    'branch_code', b.branch_code,
                                    'mobile_number', b.mobile_number,
                                    'is_primary', b.is_primary,
                                    'is_verified', b.is_verified
                                )
                            ),'[]'::json
                        )
                    ")->whereColumn('b.seller_id', 'p.seller_id');
            }, 'seller_bank_accounts');

        if (!empty($filters['seller_id'])) {
            $query->where('p.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('st.value', $filters['status']);
        }

        return $query->orderByDesc('p.id')->get();
    }


    public function summary(): array
    {
        $rows = DB::table('seller_payouts as p')
            ->leftJoin('master_types as st','st.id','=','p.status_id')
            ->select('st.value as status')
            ->selectRaw('COUNT(p.id) as total_requests')
            ->selectRaw('COALESCE(SUM(p.net_amount), 0) as total_amount')
            ->groupBy('st.value')->orderBy('st.value')->get();

        return [
            'total_requests' => (int) $rows->sum('total_requests'),
            'total_amount' => (float) $rows->sum('total_amount'),
            'by_status' => $rows,
        ];
    }

    private function updateStatus(int $payoutId, string $newStatus, array $fields = [], array $allowedCurrentStatuses = []): object {
        return DB::transaction(function () use ($payoutId, $newStatus, $fields, $allowedCurrentStatuses)
        {
            $payout = DB::table('seller_payouts')->where('id', $payoutId)->lockForUpdate()->first();

            if (!$payout) {
                abort(404, 'Payout request not found.');
            }

            $currentStatus = DB::table('master_types')->where('id', $payout->status_id)->where('key', 'payout_status')->value('value');

            if (!in_array($currentStatus, $allowedCurrentStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => "Payout cannot change from '{$currentStatus}' to '{$newStatus}'.",
                ]);
            }

            DB::table('seller_payouts')->where('id', $payoutId)
                ->update(array_merge($fields, [
                    'status_id' => $this->statusId($newStatus),
                    'updated_at' => now(),
                ]));

            return DB::table('seller_payouts')->where('id', $payoutId)->first();
        });
    }

    public function approve(int $id): object
    {
        return $this->updateStatus(
            $id,
            'approved',
            ['processed_at' => now()],
            ['pending']
        );
    }

    public function reject(int $id, string $reason): object
    {
        return $this->updateStatus(
            $id,
            'rejected',
            ['failure_reason' => $reason],
            ['pending', 'approved']
        );
    }

    public function markProcessing(int $id, array $data = []): object
    {
        return $this->updateStatus($id, 'processing',
            [
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'processed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ],
            ['approved']
        );
    }

    public function markPaid(int $id, array $data): object
    {
        return $this->updateStatus($id, 'paid',
            [
                'transaction_id' => $data['transaction_id'],
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'paid_at' => now(),
                'failure_reason' => null,
            ],
            ['approved', 'processing']
        );
    }
}
