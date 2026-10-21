<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerSuspensionService
{
    private function findSellerStatusId(string $value): int
    {
        $id = DB::table('master_types')
            ->where('key', 'seller_status')
            ->where('value', $value)
            ->where('is_active', true)
            ->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'status' => ["Seller status '{$value}' was not found or is inactive."]
            ]);
        }

        return (int) $id;
    }

    public function getAll(array $filters = []): array
    {
        $suspendedStatusId = $this->findSellerStatusId('Suspended');

        $query = DB::table('sellers as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 's.status_id')
            ->select(
                's.id as seller_id',
                's.user_id',
                'u.name',
                'u.email',
                'u.phone',
                's.status_id',
                'mt.value as status',
                'mt.description as status_description'
            );

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'ILIKE', "%{$search}%")
                    ->orWhere('u.email', 'ILIKE', "%{$search}%")
                    ->orWhereRaw('CAST(s.id AS TEXT) ILIKE ?', ["%{$search}%"]);
            });
        }

        if (array_key_exists('is_suspended', $filters)
            && $filters['is_suspended'] !== null
            && $filters['is_suspended'] !== '') {
            $isSuspended = filter_var(
                $filters['is_suspended'],
                FILTER_VALIDATE_BOOLEAN
            );

            if ($isSuspended) {
                $query->where('s.status_id', $suspendedStatusId);
            } else {
                $query->where('s.status_id', '!=', $suspendedStatusId);
            }
        }

        $sellers = $query
            ->orderByDesc('s.id')
            ->get()
            ->map(function ($seller) {
                $history = DB::table('seller_suspension_histories')
                    ->where('seller_id', $seller->seller_id)
                    ->whereNull('reactivated_at')
                    ->orderByDesc('id')
                    ->first();

                $seller->is_suspended = $history !== null;
                $seller->suspension_reason = $history->reason ?? null;
                $seller->suspended_at = $history->suspended_at ?? null;

                return $seller;
            });

        return [
            'total' => $sellers->count(),
            'sellers' => $sellers,
        ];
    }

    public function getById(int $sellerId): array
    {
        $seller = DB::table('sellers as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 's.status_id')
            ->where('s.id', $sellerId)
            ->select(
                's.id as seller_id',
                's.user_id',
                'u.name',
                'u.email',
                'u.phone',
                's.status_id',
                'mt.value as status',
                'mt.description as status_description'
            )
            ->first();

        if (!$seller) {
            throw ValidationException::withMessages([
                'seller_id' => ['Seller not found.']
            ]);
        }

        $seller->suspension_history = DB::table('seller_suspension_histories')
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->get();

        $seller->is_suspended = DB::table('seller_suspension_histories')
            ->where('seller_id', $sellerId)
            ->whereNull('reactivated_at')
            ->exists();

        return [
            'seller' => $seller,
        ];
    }

    public function suspend(int $sellerId, string $reason): array
    {
        return DB::transaction(function () use ($sellerId, $reason) {
            $seller = DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$seller) {
                throw ValidationException::withMessages([
                    'seller_id' => ['Seller not found.']
                ]);
            }

            $suspendedStatusId = $this->findSellerStatusId('Suspended');

            if ((int) $seller->status_id === $suspendedStatusId) {
                throw ValidationException::withMessages([
                    'seller_id' => ['Seller is already suspended.']
                ]);
            }

            $activeHistory = DB::table('seller_suspension_histories')
                ->where('seller_id', $sellerId)
                ->whereNull('reactivated_at')
                ->exists();

            if ($activeHistory) {
                throw ValidationException::withMessages([
                    'seller_id' => ['Seller already has an active suspension.']
                ]);
            }

            $adminId = auth()->id();

            $historyId = DB::table('seller_suspension_histories')
                ->insertGetId([
                    'seller_id' => $sellerId,
                    'previous_status_id' => $seller->status_id,
                    'suspended_status_id' => $suspendedStatusId,
                    'reason' => $reason,
                    'action_by' => $adminId,
                    'suspended_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('sellers')
                ->where('id', $sellerId)
                ->update([
                    'status_id' => $suspendedStatusId,
                ]);

            return [
                'seller_id' => $sellerId,
                'status' => 'Suspended',
                'reason' => $reason,
                'history_id' => $historyId,
                'suspended_at' => now()->toDateTimeString(),
            ];
        });
    }

    public function reactivate(int $sellerId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($sellerId, $reason) {
            $seller = DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$seller) {
                throw ValidationException::withMessages([
                    'seller_id' => ['Seller not found.']
                ]);
            }

            $suspendedStatusId = $this->findSellerStatusId('Suspended');

            $history = DB::table('seller_suspension_histories')
                ->where('seller_id', $sellerId)
                ->whereNull('reactivated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$history || (int) $seller->status_id !== $suspendedStatusId) {
                throw ValidationException::withMessages([
                    'seller_id' => ['Seller is not currently suspended.']
                ]);
            }

            // Restore the status that existed before suspension.
            $restoreStatusId = $history->previous_status_id;

            if (!$restoreStatusId) {
                $restoreStatusId = $this->findSellerStatusId('Approved');
            }

            $now = now();
            $adminId = auth()->id();

            DB::table('sellers')
                ->where('id', $sellerId)
                ->update([
                    'status_id' => $restoreStatusId,
                ]);

            DB::table('seller_suspension_histories')
                ->where('id', $history->id)
                ->update([
                    'reactivated_at' => $now,
                    'reactivated_by' => $adminId,
                    'reactivation_reason' => $reason,
                    'updated_at' => $now,
                ]);

            $restoredStatus = DB::table('master_types')
                ->where('id', $restoreStatusId)
                ->value('value');

            return [
                'seller_id' => $sellerId,
                'status' => $restoredStatus,
                'reason' => $reason,
                'reactivated_at' => $now->toDateTimeString(),
            ];
        });
    }
}
