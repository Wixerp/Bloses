<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class StoreManagementService
{
    private function sellerId(): int
    {
        $seller = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->first();

        if (!$seller) {
            throw ValidationException::withMessages([
                'seller' => ['Seller profile not found for this account.'],
            ]);
        }

        return (int) $seller->id;
    }

    private function storeQuery()
    {
        return DB::table('seller_store_settings')
            ->where('seller_id', $this->sellerId());
    }

    public function getMyStore(): array
    {
        $store = $this->storeQuery()->first();

        if (!$store) {
            throw ValidationException::withMessages([
                'store' => ['Store settings not found for this seller.'],
            ]);
        }

        return (array) $store;
    }

    public function updateMyStore(array $data): array
    {
        $store = $this->storeQuery()->first();

        if (!$store) {
            throw ValidationException::withMessages([
                'store' => ['Store settings not found. Please contact support.'],
            ]);
        }

        $updates = [
            'updated_at' => now(),
        ];

        $allowed = [
            'store_name',
            'store_description',
            'logo',
            'banner',
            'email',
            'phone',
            'website',
            'facebook_url',
            'instagram_url',
            'twitter_url',
            'youtube_url',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('store_name', $data)) {
            $baseSlug = Str::slug($data['store_name']);

            if ($baseSlug === '') {
                throw ValidationException::withMessages([
                    'store_name' => ['Store name must contain valid characters.'],
                ]);
            }

            $slug = $baseSlug;
            $counter = 1;

            while (
                DB::table('seller_store_settings')
                    ->where('store_slug', $slug)
                    ->where('id', '<>', $store->id)
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $updates['store_slug'] = $slug;
        }

        $this->storeQuery()->update($updates);

        return $this->getMyStore();
    }

    public function getPublicStore(string $slug): ?array
    {
        $store = DB::table('seller_store_settings as st')
            ->join('sellers as s', 's.id', '=', 'st.seller_id')
            ->where('st.store_slug', $slug)
            ->where('st.is_active', true)
            ->select([
                'st.store_name',
                'st.store_slug',
                'st.store_description',
                'st.logo',
                'st.banner',
                'st.website',
                'st.facebook_url',
                'st.instagram_url',
                'st.twitter_url',
                'st.youtube_url',
                'st.is_verified',
                'st.verified_at',
                's.business_name',
                's.description as business_description',
            ])
            ->first();

        return $store ? (array) $store : null;
    }
}
