<?php

namespace App\Services\Seller;

use App\Models\Seller;
use App\Models\SellerAddress;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\SellerStoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerRegistrationService
{
    public function register(array $data, array $files)
    {
        return DB::transaction(function () use ($data, $files) {
            $user = auth()->user();
            $seller = Seller::create([
                'user_id'        => $user->id,
                'business_name'  => $data['business_name'],
                'slug'           => Str::slug($data['business_name']) . '-' . $user->id,
                'business_email' => $data['business_email'] ?? $user->email,
                'business_phone' => $data['business_phone'] ?? $user->phone,
                'description'    => $data['business_description'] ?? null,
                'status_id'      => 35, //Pending Approval
                'approved_at'    => null,
            ]);

            SellerAddress::create([
                'seller_id'      => $seller->id,
                'state_id'       => $data['province'],
                'city_id'        => $data['city'],
                // 'state_id'       => 1,
                // 'city_id'        => 1,
                'address_line_1' => $data['address'],
                'address_line_2' => null,
                'area'           => $data['area'] ?? null,
                'postal_code'    => $data['postal_code'] ?? null,
                'country'        => $data['country'],
                'contact_person' => $user->name,
                'phone'          => $data['mobile'],
                'is_primary'     => true,
            ]);

            SellerBankAccount::create([
                'seller_id'           => $seller->id,
                'account_type_id'     => 30,
                'account_holder_name' => $data['account_holder'],
                'bank_name'           => $data['bank_name'],
                'account_number'      => $data['account_number'],
                'iban'                => $data['iban'],
                'branch_name'         => $data['branch_name'],
                'branch_code'         => $data['branch_code'],
                'mobile_number'       => $data['mobile'],
                'is_primary'          => true,
                'is_verified'         => false,
                'verified_at'         => null,
            ]);

            $documentTypes = [
                'cnic_front' => 19,
                'cnic_back'  => 20,
            ];

            foreach ($documentTypes as $field => $documentTypeId) {

                if (!isset($files[$field])) {
                    continue;
                }

                $file = $files[$field];

                $path = $file->store(
                    'sellers/' . $seller->id . '/documents',
                    'public'
                );

                SellerDocument::create([
                    'seller_id'          => $seller->id,
                    'document_type_id'   => $documentTypeId,
                    'status_id'          => null,
                    'document_number'    => $data['cnic_number'],
                    'file_path'          => $path,
                    'original_file_name' => $file->getClientOriginalName(),
                    'mime_type'          => $file->getClientMimeType(),
                    'file_size'          => $file->getSize(),
                    'admin_note'         => null,
                    'verified_at'        => null,
                    'verified_by'        => null,
                ]);
            }

            $logoPath = null;
            $bannerPath = null;

            if (isset($files['store_logo'])) {
                $logoPath = $files['store_logo']->store(
                    'sellers/' . $seller->id . '/store',
                    'public'
                );
            }

            if (isset($files['store_banner'])) {
                $bannerPath = $files['store_banner']->store(
                    'sellers/' . $seller->id . '/store',
                    'public'
                );
            }

            SellerStoreSetting::create([
                'seller_id'         => $seller->id,
                'store_name'        => $data['store_name'],
                'store_slug'        => Str::slug($data['store_name']) . '-' . $user->id,
                'store_description' => $data['store_description'] ?? null,
                'logo'              => $logoPath,
                'banner'             => $bannerPath,
                'email'             => $data['business_email'] ?? $user->email,
                'phone'             => $data['business_phone'] ?? $user->phone,
                'is_active'         => true,
                'is_verified'       => false,
                'verified_at'       => null,
            ]);

            return $seller->load([
                'user',
                'addresses',
                'bankAccounts',
                'documents',
                'storeSettings',
            ]);
        });
    }
}
