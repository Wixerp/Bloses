<?php

namespace App\Services\Buyer;

use Illuminate\Support\Facades\DB;

class AddressService
{
    public function getAddresses()
    {
        if (!auth()->check()) {
            abort(401, 'Please login to view your addresses.');
        }

        $userId = auth()->id();
        return DB::table('addresses')->where('user_id', $userId)->orderByDesc('is_default')->orderByDesc('id')->get();
    }

    public function createAddress(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to add an address.');
        }

        $userId = auth()->id();
        return DB::transaction(function () use ($data, $userId)
        {
            $isDefault = $data['is_default'] ?? false;
            if ($isDefault) {
                DB::table('addresses')->where('user_id', $userId)
                    ->update([
                        'is_default' => false,
                        'updated_at' => now(),
                    ]);
            }

            $hasAddress = DB::table('addresses')->where('user_id', $userId)->exists();
            if (!$hasAddress) {
                $isDefault = true;
            }

            $addressId = DB::table('addresses')->insertGetId([
                'user_id' => $userId,
                'address_type_id' => $data['address_type_id'],
                'label' => $data['label'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'country_id' => $data['country_id'],
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_default' => $isDefault,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('addresses')->where('id', $addressId)->first();
        });
    }

    public function updateAddress(int $addressId, array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to update your address.');
        }

        $userId = auth()->id();
        $address = DB::table('addresses')->where('id', $addressId)->where('user_id', $userId)->first();

        if (!$address) {
            abort(404, 'Address not found.');
        }

        return DB::transaction(function () use ($addressId, $userId, $data)
        {
            $isDefault = $data['is_default'] ?? false;
            if ($isDefault) {
                DB::table('addresses')->where('user_id', $userId)->where('id', '!=', $addressId)
                    ->update([
                        'is_default' => false,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('addresses')->where('id', $addressId)->where('user_id', $userId)
                ->update([
                    'address_type_id' => $data['address_type_id'],
                    'label' => $data['label'] ?? null,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'],
                    'address_line_1' => $data['address_line_1'],
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'country_id' => $data['country_id'],
                    'state_id' => $data['state_id'] ?? null,
                    'city_id' => $data['city_id'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'is_default' => $isDefault,
                    'updated_at' => now(),
                ]);

            return DB::table('addresses')->where('id', $addressId)->first();
        });
    }

    public function deleteAddress(int $addressId): void
    {
        if (!auth()->check()) {
            abort(401, 'Please login to delete your address.');
        }

        $userId = auth()->id();
        $address = DB::table('addresses')->where('id', $addressId)->where('user_id', $userId)->first();

        if (!$address) {
            abort(404, 'Address not found.');
        }

        DB::table('addresses')->where('id', $addressId)->where('user_id', $userId)->delete();

        if ($address->is_default) {
            $newDefault = DB::table('addresses')->where('user_id', $userId)->orderByDesc('id')->first();
            if ($newDefault) {
                DB::table('addresses')->where('id', $newDefault->id)
                    ->update([
                        'is_default' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
