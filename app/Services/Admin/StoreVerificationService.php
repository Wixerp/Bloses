<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StoreVerificationService
{
    public function getAll(array $filters = [])
    {
        $query = DB::table('seller_store_settings as ss')
            ->join('sellers as s', 's.id', '=', 'ss.seller_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select([
                'ss.id as store_id',
                'ss.seller_id',
                'ss.is_verified',
                's.user_id',
                'u.name as seller_name',
                'u.email as seller_email',
            ]);

        // Store name column is included only if it exists.
        if (Schema::hasColumn('seller_store_settings', 'store_name')) {
            $query->addSelect('ss.store_name');
        }

        if (isset($filters['is_verified']) &&
            $filters['is_verified'] !== '') {
            $query->where(
                'ss.is_verified',
                filter_var($filters['is_verified'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        if (!empty($filters['seller_id'])) {
            $query->where('ss.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'ILIKE', "%{$search}%")
                    ->orWhere('u.email', 'ILIKE', "%{$search}%");

                if (Schema::hasColumn(
                    'seller_store_settings',
                    'store_name'
                )) {
                    $q->orWhere('ss.store_name', 'ILIKE', "%{$search}%");
                }
            });
        }

        return $query
            ->orderByDesc('ss.id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 10), 1), 100));
    }

    public function getById(int $storeId): ?object
    {
        $query = DB::table('seller_store_settings as ss')
            ->join('sellers as s', 's.id', '=', 'ss.seller_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ss.id', $storeId)
            ->select([
                'ss.id as store_id',
                'ss.seller_id',
                'ss.is_verified',
                's.user_id',
                'u.name as seller_name',
                'u.email as seller_email',
            ]);

        if (Schema::hasColumn('seller_store_settings', 'store_name')) {
            $query->addSelect('ss.store_name');
        }

        return $query->first();
    }

    public function updateVerification(int $storeId, bool $isVerified): object
    {
        $store = DB::table('seller_store_settings')
            ->where('id', $storeId)
            ->first();

        if (!$store) {
            throw ValidationException::withMessages([
                'store_id' => ['Store not found.'],
            ]);
        }

        $updates = [
            'is_verified' => $isVerified,
        ];

        if (Schema::hasColumn('seller_store_settings', 'verified_at')) {
            $updates['verified_at'] = $isVerified ? now() : null;
        }

        DB::table('seller_store_settings')
            ->where('id', $storeId)
            ->update($updates);

        return $this->getById($storeId);
    }
}
