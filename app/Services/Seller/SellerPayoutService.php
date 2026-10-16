<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerPayoutService
{
    private function getSellerId(): int
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', Auth::id())
            ->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        return (int) $sellerId;
    }

    private function getStatusId(string $value): int
    {
        $id = DB::table('master_types')
            ->where('key', 'payout_status')
            ->where('value', $value)
            ->value('id');

        if (!$id) {
            throw new \RuntimeException(
                "Payout status '{$value}' not found."
            );
        }

        return (int) $id;
    }

    private function getEarningStatusId(string $value): int
    {
        $id = DB::table('master_types')
            ->where('key', 'earning_status')
            ->where('value', $value)
            ->value('id');

        if (!$id) {
            throw new \RuntimeException(
                "Earning status '{$value}' not found."
            );
        }

        return (int) $id;
    }

    /**
     * Statuses whose allocations continue to consume balance.
     */
    private function activePayoutStatusIds(): array
    {
        return collect([
            $this->getStatusId('pending'),
            $this->getStatusId('approved'),
            $this->getStatusId('processing'),
            $this->getStatusId('paid'),
        ])->unique()->values()->all();
    }

    /**
     * Available balance = eligible earnings - active allocations.
     * Partial allocations are supported.
     */
    public function availableBalance(): float
    {
        $sellerId = $this->getSellerId();

        $availableId = $this->getEarningStatusId('available');
        $reservedId = $this->getEarningStatusId('reserved');

        $earningTotal = (float) DB::table('seller_earnings')
            ->where('seller_id', $sellerId)
            ->whereIn('status_id', [$availableId, $reservedId])
            ->sum('net_amount');

        $allocatedTotal = (float) DB::table('seller_payout_items as item')
            ->join(
                'seller_payouts as payout',
                'payout.id',
                '=',
                'item.seller_payout_id'
            )
            ->where('payout.seller_id', $sellerId)
            ->whereIn('payout.status_id', $this->activePayoutStatusIds())
            ->sum('item.amount');

        return round(max(0, $earningTotal - $allocatedTotal), 2);
    }

    /**
     * Seller payout history. No pagination.
     */
    public function listing(): object
    {
        $sellerId = $this->getSellerId();

        return DB::table('seller_payouts')
            ->leftJoin(
                'master_types as payout_status',
                'payout_status.id',
                '=',
                'seller_payouts.status_id'
            )
            ->leftJoin(
                'seller_bank_accounts as bank',
                'bank.id',
                '=',
                'seller_payouts.seller_bank_account_id'
            )
            ->where('seller_payouts.seller_id', $sellerId)
            ->select([
                'seller_payouts.id',
                'seller_payouts.payout_number',
                'seller_payouts.gross_amount',
                'seller_payouts.commission_amount',
                'seller_payouts.deduction_amount',
                'seller_payouts.net_amount',
                'seller_payouts.currency',
                'seller_payouts.transaction_id',
                'seller_payouts.payment_gateway',
                'seller_payouts.notes',
                'seller_payouts.failure_reason',
                'seller_payouts.processed_at',
                'seller_payouts.paid_at',
                'seller_payouts.failed_at',
                'seller_payouts.created_at',
                'payout_status.value as status',
                'payout_status.description as status_name',
                'bank.bank_name',
                'bank.account_holder_name',
                'bank.account_number',
                'bank.iban',
            ])
            ->orderByDesc('seller_payouts.id')
            ->get();
    }

    /**
     * Create a payout and allocate the requested amount
     * against the seller's eligible earnings.
     */
    public function requestPayout(array $data): object
    {
        $sellerId = $this->getSellerId();

        return DB::transaction(function () use ($sellerId, $data) {

            // Serialize payout requests for the same seller.
            DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            $bank = DB::table('seller_bank_accounts')
                ->where('id', $data['seller_bank_account_id'])
                ->where('seller_id', $sellerId)
                ->where('is_verified', false)
                ->lockForUpdate()
                ->first();

            if (!$bank) {
                throw ValidationException::withMessages([
                    'seller_bank_account_id' => [
                        'Please select your own verified bank account.',
                    ],
                ]);
            }

            $amountCents = (int) round(
                (float) $data['amount'] * 100
            );

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than zero.'],
                ]);
            }

            $availableId = $this->getEarningStatusId('available');
            $reservedId = $this->getEarningStatusId('reserved');

            // Lock earnings in consistent ID order.
            $earnings = DB::table('seller_earnings')
                ->where('seller_id', $sellerId)
                ->whereIn('status_id', [$availableId, $reservedId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $activeStatusIds = $this->activePayoutStatusIds();

            $allocatedByEarning = DB::table('seller_payout_items as item')
                ->join(
                    'seller_payouts as payout',
                    'payout.id',
                    '=',
                    'item.seller_payout_id'
                )
                ->where('payout.seller_id', $sellerId)
                ->whereIn('payout.status_id', $activeStatusIds)
                ->selectRaw(
                    'item.seller_earning_id, SUM(item.amount) as allocated'
                )
                ->groupBy('item.seller_earning_id')
                ->pluck('allocated', 'seller_earning_id');

            $eligible = [];
            $totalAvailableCents = 0;

            foreach ($earnings as $earning) {
                $netCents = (int) round(
                    (float) $earning->net_amount * 100
                );

                $allocatedCents = (int) round(
                    (float) ($allocatedByEarning[$earning->id] ?? 0) * 100
                );

                $remainingCents = max(
                    0,
                    $netCents - $allocatedCents
                );

                if ($remainingCents > 0) {
                    $eligible[] = [
                        'id' => (int) $earning->id,
                        'remaining_cents' => $remainingCents,
                    ];

                    $totalAvailableCents += $remainingCents;
                }
            }

            if ($amountCents > $totalAvailableCents) {
                throw ValidationException::withMessages([
                    'amount' => [
                        'Insufficient available balance. Available: PKR '
                        . number_format($totalAvailableCents / 100, 2),
                    ],
                ]);
            }

            $payoutId = DB::table('seller_payouts')->insertGetId([
                'seller_id' => $sellerId,
                'seller_bank_account_id' => $bank->id,
                'status_id' => $this->getStatusId('pending'),
                'gross_amount' => number_format(
                    $amountCents / 100, 2, '.', ''
                ),
                'commission_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => number_format(
                    $amountCents / 100, 2, '.', ''
                ),
                'currency' => $data['currency'] ?? 'PKR',
                'payout_number' => 'PO-' . strtoupper(Str::random(16)),
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');

            $remainingRequestCents = $amountCents;
            $affectedEarningIds = [];

            foreach ($eligible as $earning) {
                if ($remainingRequestCents <= 0) {
                    break;
                }

                $allocateCents = min(
                    $remainingRequestCents,
                    $earning['remaining_cents']
                );

                DB::table('seller_payout_items')->insert([
                    'seller_payout_id' => $payoutId,
                    'seller_earning_id' => $earning['id'],
                    'amount' => number_format(
                        $allocateCents / 100, 2, '.', ''
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $remainingRequestCents -= $allocateCents;
                $affectedEarningIds[] = $earning['id'];
            }

            if ($remainingRequestCents !== 0) {
                throw new \RuntimeException(
                    'Payout allocation did not match requested amount.'
                );
            }

            $this->refreshEarningStatuses(
                $affectedEarningIds,
                $sellerId
            );

            return DB::table('seller_payouts as payout')
                ->leftJoin(
                    'master_types as status',
                    'status.id',
                    '=',
                    'payout.status_id'
                )
                ->where('payout.id', $payoutId)
                ->select([
                    'payout.id',
                    'payout.payout_number',
                    'payout.gross_amount',
                    'payout.commission_amount',
                    'payout.deduction_amount',
                    'payout.net_amount',
                    'payout.currency',
                    'payout.notes',
                    'payout.created_at',
                    'status.value as status',
                    'status.description as status_name',
                ])
                ->first();
        }, 3);
    }

    /**
     * Recalculate statuses after admin approves, rejects,
     * fails, or marks a payout as paid.
     *
     * Call this from the admin payout service in the same
     * database transaction as the payout status change.
     */
    public function refreshEarningStatuses(
        array $earningIds,
        int $sellerId
    ): void {
        $availableId = $this->getEarningStatusId('available');
        $reservedId = $this->getEarningStatusId('reserved');
        $paidId = $this->getEarningStatusId('paid');

        $pendingId = $this->getStatusId('pending');
        $approvedId = $this->getStatusId('approved');
        $processingId = $this->getStatusId('processing');
        $payoutPaidId = $this->getStatusId('paid');

        $activeStatusIds = [
            $pendingId,
            $approvedId,
            $processingId,
            $payoutPaidId,
        ];

        foreach (array_unique($earningIds) as $earningId) {
            $earning = DB::table('seller_earnings')
                ->where('id', $earningId)
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$earning) {
                continue;
            }

            // Do not overwrite refunded or cancelled earnings.
            if (!in_array((int) $earning->status_id, [
                $availableId,
                $reservedId,
                $paidId,
            ], true)) {
                continue;
            }

            $netCents = (int) round(
                (float) $earning->net_amount * 100
            );

            $totals = DB::table('seller_payout_items as item')
                ->join(
                    'seller_payouts as payout',
                    'payout.id',
                    '=',
                    'item.seller_payout_id'
                )
                ->where('item.seller_earning_id', $earningId)
                ->whereIn('payout.status_id', $activeStatusIds)
                ->selectRaw(
                    'COALESCE(SUM(item.amount), 0) as active_amount'
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN payout.status_id = ? THEN item.amount ELSE 0 END), 0) as paid_amount',
                    [$payoutPaidId]
                )
                ->first();

            $activeCents = (int) round(
                (float) $totals->active_amount * 100
            );

            $paidCents = (int) round(
                (float) $totals->paid_amount * 100
            );

            if ($netCents > 0 && $paidCents >= $netCents) {
                $newStatusId = $paidId;
            } elseif ($netCents > 0 && $activeCents >= $netCents) {
                $newStatusId = $reservedId;
            } else {
                $newStatusId = $availableId;
            }

            DB::table('seller_earnings')
                ->where('id', $earningId)
                ->update([
                    'status_id' => $newStatusId,
                    'updated_at' => now(),
                ]);
        }
    }
}
