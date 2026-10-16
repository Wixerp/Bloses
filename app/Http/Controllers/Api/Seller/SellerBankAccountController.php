<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerBankAccountController extends Controller
{
    /**
     * Get logged-in seller ID.
     */
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

    /**
     * List the logged-in seller's bank accounts.
     * GET /api/mobile/seller/bank-accounts
     */
    public function index(): JsonResponse
    {
        $sellerId = $this->getSellerId();

        $accounts = DB::table('seller_bank_accounts')
            ->where('seller_id', $sellerId)
            ->select([
                'id',
                'account_type_id',
                'account_holder_name',
                'bank_name',
                'account_number',
                'iban',
                'branch_name',
                'branch_code',
                'mobile_number',
                'is_primary',
                'is_verified',
                'verified_at',
                'created_at',
            ])
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Bank accounts fetched successfully.',
            'data' => $accounts,
        ]);
    }

    /**
     * Add a bank account.
     * POST /api/mobile/seller/bank-accounts
     */
    public function store(Request $request): JsonResponse
    {
        $sellerId = $this->getSellerId();

        $data = $request->validate([
            'account_type_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'account_holder_name' => [
                'required',
                'string',
                'max:255',
            ],
            'bank_name' => [
                'required',
                'string',
                'max:255',
            ],
            'account_number' => [
                'required',
                'string',
                'max:100',
            ],
            'iban' => [
                'nullable',
                'string',
                'max:34',
            ],
            'branch_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'mobile_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'is_primary' => [
                'sometimes',
                'boolean',
            ],
        ]);

        return DB::transaction(function () use (
            $data,
            $sellerId
        ) {
            // Serialize account changes for this seller.
            DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            $makePrimary = (bool) ($data['is_primary'] ?? false);

            $hasAccounts = DB::table('seller_bank_accounts')
                ->where('seller_id', $sellerId)
                ->exists();

            // First account automatically becomes primary.
            if (!$hasAccounts) {
                $makePrimary = true;
            }

            // Do not trust client-supplied verification fields.
            unset($data['is_verified'], $data['verified_at']);

            if ($makePrimary) {
                DB::table('seller_bank_accounts')
                    ->where('seller_id', $sellerId)
                    ->update([
                        'is_primary' => false,
                        'updated_at' => now(),
                    ]);
            }

            $accountId = DB::table('seller_bank_accounts')
                ->insertGetId([
                    'seller_id' => $sellerId,
                    'account_type_id' => $data['account_type_id'],
                    'account_holder_name' => $data['account_holder_name'],
                    'bank_name' => $data['bank_name'],
                    'account_number' => $data['account_number'],
                    'iban' => $data['iban'] ?? null,
                    'branch_name' => $data['branch_name'] ?? null,
                    'branch_code' => $data['branch_code'] ?? null,
                    'mobile_number' => $data['mobile_number'] ?? null,
                    'is_primary' => $makePrimary,
                    'is_verified' => false,
                    'verified_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id');

            $account = DB::table('seller_bank_accounts')
                ->where('id', $accountId)
                ->where('seller_id', $sellerId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Bank account added successfully. Verification is pending.',
                'data' => $account,
            ], 201);
        });
    }

    /**
     * Get one bank account.
     * GET /api/mobile/seller/bank-accounts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $sellerId = $this->getSellerId();

        $account = DB::table('seller_bank_accounts')
            ->where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank account fetched successfully.',
            'data' => $account,
        ]);
    }

    /**
     * Update an unverified bank account.
     * PUT /api/mobile/seller/bank-accounts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sellerId = $this->getSellerId();

        $data = $request->validate([
            'account_type_id' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'account_holder_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'bank_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'iban' => [
                'sometimes',
                'nullable',
                'string',
                'max:34',
            ],
            'branch_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'branch_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'mobile_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'is_primary' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $account = DB::table('seller_bank_accounts')
            ->where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found.',
            ], 404);
        }

        if ($account->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Verified bank accounts cannot be edited. Contact support if changes are required.',
            ], 422);
        }

        return DB::transaction(function () use (
            $data,
            $sellerId,
            $id
        ) {
            DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            $makePrimary = (bool) (
                $data['is_primary'] ?? false
            );

            if ($makePrimary) {
                DB::table('seller_bank_accounts')
                    ->where('seller_id', $sellerId)
                    ->where('id', '!=', $id)
                    ->update([
                        'is_primary' => false,
                        'updated_at' => now(),
                    ]);
            }

            unset($data['is_verified'], $data['verified_at']);

            if (array_key_exists('is_primary', $data)) {
                $data['is_primary'] = $makePrimary;
            }

            $data['updated_at'] = now();

            DB::table('seller_bank_accounts')
                ->where('id', $id)
                ->where('seller_id', $sellerId)
                ->update($data);

            $updated = DB::table('seller_bank_accounts')
                ->where('id', $id)
                ->where('seller_id', $sellerId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Bank account updated successfully.',
                'data' => $updated,
            ]);
        });
    }

    /**
     * Delete an unverified bank account.
     * DELETE /api/mobile/seller/bank-accounts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $sellerId = $this->getSellerId();

        return DB::transaction(function () use (
            $id,
            $sellerId
        ) {
            DB::table('sellers')
                ->where('id', $sellerId)
                ->lockForUpdate()
                ->first();

            $account = DB::table('seller_bank_accounts')
                ->where('id', $id)
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found.',
                ], 404);
            }

            if ($account->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verified bank accounts cannot be deleted.',
                ], 422);
            }

            $hasPayouts = DB::table('seller_payouts')
                ->where('seller_bank_account_id', $id)
                ->exists();

            if ($hasPayouts) {
                return response()->json([
                    'success' => false,
                    'message' => 'This bank account has payout history and cannot be deleted.',
                ], 422);
            }

            DB::table('seller_bank_accounts')
                ->where('id', $id)
                ->where('seller_id', $sellerId)
                ->delete();

            // Promote another account if the deleted one was primary.
            if ($account->is_primary) {
                $nextAccount = DB::table('seller_bank_accounts')
                    ->where('seller_id', $sellerId)
                    ->orderByDesc('id')
                    ->first();

                if ($nextAccount) {
                    DB::table('seller_bank_accounts')
                        ->where('id', $nextAccount->id)
                        ->update([
                            'is_primary' => true,
                            'updated_at' => now(),
                        ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully.',
            ]);
        });
    }
}
