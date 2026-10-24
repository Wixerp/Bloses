<?php

namespace App\Services\Seller;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BusinessVerificationService
{
    private function sellerId(): int
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        if (!$sellerId) {
            throw ValidationException::withMessages([
                'seller' => 'Seller profile not found.',
            ]);
        }

        return (int) $sellerId;
    }

    public function getVerification(): array
    {
        $sellerId = $this->sellerId();

        $seller = DB::table('sellers as s')
            ->leftJoin('master_types as mt', 'mt.id', '=', 's.status_id')
            ->where('s.id', $sellerId)
            ->select(
                's.id',
                's.business_name',
                's.slug',
                's.business_email',
                's.business_phone',
                's.description',
                's.status_id',
                'mt.value as status',
                's.approved_at',
                's.suspended_at',
                's.suspension_reason',
                's.admin_note',
                's.created_at'
            )
            ->first();

        $store = DB::table('seller_store_settings')
            ->where('seller_id', $sellerId)
            ->select(
                'id',
                'store_name',
                'is_verified',
                'verified_at'
            )
            ->first();

        $documents = DB::table('seller_documents as d')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'd.status_id')
            ->where('d.seller_id', $sellerId)
            ->orderByDesc('d.id')
            ->select(
                'd.id',
                'd.document_type_id',
                'd.status_id',
                'mt.value as status',
                'd.document_number',
                'd.original_file_name',
                'd.mime_type',
                'd.file_size',
                'd.admin_note',
                'd.verified_at',
                'd.created_at'
            )
            ->get()
            ->map(function ($document) {
                $document->file_url = Storage::disk('public')
                    ->url($this->documentPath((int) $document->id));
                return $document;
            });

        return [
            'business' => $seller,
            'store_verification' => $store,
            'documents' => $documents,
        ];
    }

    private function documentPath(int $documentId): string
    {
        $path = DB::table('seller_documents')
            ->where('id', $documentId)
            ->value('file_path');

        return $path ?: '';
    }

    public function updateBusiness(array $data): object
    {
        $sellerId = $this->sellerId();

        $updates = [];

        foreach ([
            'business_name',
            'business_email',
            'business_phone',
            'description',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (!$updates) {
            throw ValidationException::withMessages([
                'business' => 'Provide at least one business field to update.',
            ]);
        }

        $updates['updated_at'] = now();

        DB::table('sellers')
            ->where('id', $sellerId)
            ->update($updates);

        return DB::table('sellers')
            ->where('id', $sellerId)
            ->first();
    }

    public function getAddresses(): \Illuminate\Support\Collection
    {
        return DB::table('seller_addresses')
            ->where('seller_id', $this->sellerId())
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();
    }

    public function saveAddress(array $data, ?int $addressId = null): object
    {
        $sellerId = $this->sellerId();

        return DB::transaction(function () use ($data, $sellerId, $addressId) {
            if (!empty($data['is_primary'])) {
                DB::table('seller_addresses')
                    ->where('seller_id', $sellerId)
                    ->update(['is_primary' => false]);
            }

            $values = [
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'area' => $data['area'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'],
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'updated_at' => now(),
            ];

            if ($addressId) {
                $updated = DB::table('seller_addresses')
                    ->where('id', $addressId)
                    ->where('seller_id', $sellerId)
                    ->update($values);

                if (!$updated) {
                    throw ValidationException::withMessages([
                        'address' => 'Address not found for this seller.',
                    ]);
                }
            } else {
                $values['seller_id'] = $sellerId;
                $values['created_at'] = now();

                $addressId = DB::table('seller_addresses')
                    ->insertGetId($values);
            }

            return DB::table('seller_addresses')
                ->where('id', $addressId)
                ->where('seller_id', $sellerId)
                ->first();
        });
    }



    public function getBankAccounts(): \Illuminate\Support\Collection
    {
        return DB::table('seller_bank_accounts')
            ->where('seller_id', $this->sellerId())
            ->orderByDesc('is_primary')
            ->get()
            ->map(function ($account) {
                $account->account_number = $this->mask($account->account_number);
                $account->iban = $this->mask($account->iban);
                return $account;
            });
    }
    private function mask(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return str_repeat('*', max(0, strlen($value) - 4))
            . substr($value, -4);
    }

    public function saveBankAccount(array $data): object
    {
        $sellerId = $this->sellerId();

        return DB::transaction(function () use ($data, $sellerId) {
            if (!empty($data['is_primary'])) {
                DB::table('seller_bank_accounts')
                    ->where('seller_id', $sellerId)
                    ->update(['is_primary' => false]);
            }

            $id = DB::table('seller_bank_accounts')->insertGetId([
                'seller_id' => $sellerId,
                'account_type_id' => $data['account_type_id'] ?? null,
                'account_holder_name' => $data['account_holder_name'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'iban' => $data['iban'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'branch_code' => $data['branch_code'] ?? null,
                'mobile_number' => $data['mobile_number'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $account = DB::table('seller_bank_accounts')
                ->where('id', $id)
                ->first();

            $account->account_number = $this->mask($account->account_number);
            $account->iban = $this->mask($account->iban);

            return $account;
        });
    }

    public function uploadDocument(
        array $data,
        UploadedFile $file
    ): object {
        $sellerId = $this->sellerId();

        $path = $file->store('seller-documents/' . $sellerId, 'public');

        try {
            $id = DB::table('seller_documents')->insertGetId([
                'seller_id' => $sellerId,
                'document_type_id' => $data['document_type_id'] ?? null,
                'status_id' => null,
                'document_number' => $data['document_number'] ?? null,
                'file_path' => $path,
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'admin_note' => null,
                'verified_at' => null,
                'verified_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return DB::table('seller_documents')
            ->where('id', $id)
            ->select(
                'id',
                'document_type_id',
                'document_number',
                'original_file_name',
                'mime_type',
                'file_size',
                'created_at'
            )
            ->first();
    }

    public function deleteDocument(int $documentId): void
    {
        $document = DB::table('seller_documents')
            ->where('id', $documentId)
            ->where('seller_id', $this->sellerId())
            ->first();

        if (!$document) {
            throw ValidationException::withMessages([
                'document' => 'Document not found.',
            ]);
        }

        // Approved/reviewed documents should not be deleted by sellers.
        if ($document->verified_at !== null || $document->status_id !== null) {
            throw ValidationException::withMessages([
                'document' => 'This document is already under review or reviewed.',
            ]);
        }

        DB::table('seller_documents')
            ->where('id', $documentId)
            ->where('seller_id', $this->sellerId())
            ->delete();

        Storage::disk('public')->delete($document->file_path);
    }
}
