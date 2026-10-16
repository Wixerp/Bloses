<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\RegistrationStatusRequest;
use App\Http\Resources\Seller\SellerRegistrationStatusResource;
use App\Services\Seller\SellerService;
use Illuminate\Http\Request;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    public function __construct(
        protected SellerService $sellerService
    ) {}

    // public function registrationStatus(RegistrationStatusRequest $request): JsonResponse {
    //     $registered = $this->sellerService->registrationStatus(
    //         $request->user_id
    //     );

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Seller registration status retrieved successfully.',
    //         'status' => $registered,
    //     ]);
    // }

    public function registrationStatus(RegistrationStatusRequest $request): JsonResponse
    {
        $userId = auth()->id();

        $registered = $this->sellerService->registrationStatus($userId);

        return response()->json([
            'success' => true,
            'message' => 'Seller registration status retrieved successfully.',
            'user_id' => $userId,
            'status' => $registered,
        ]);
    }


    public function profileshow(): JsonResponse
    {
        $sellers = DB::table('sellers')
            ->leftJoin('users', 'users.id', '=', 'sellers.user_id')
            ->leftJoin('master_types', 'master_types.id', '=', 'sellers.status_id')
            ->select([
                'users.id as user_id','users.name','users.email','sellers.id as seller_id','sellers.business_name',
                'sellers.slug','sellers.business_email','sellers.business_phone','sellers.description',
                'sellers.status_id','master_types.value as status_name','sellers.approved_at','sellers.created_at',
                'sellers.updated_at',
            ])->get();

        if ($sellers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Seller profile not found.',
                'data' => [],
            ], 404);
        }

        $sellerIds = $sellers->pluck('seller_id');

        $addresses = DB::table('seller_addresses')->whereIn('seller_id', $sellerIds)
            ->select([
                'id','seller_id','state_id','city_id','address_line_1','address_line_2',
                'area','postal_code','country','contact_person','phone','is_primary',
            ])->get()->groupBy('seller_id');

        $bankAccounts = DB::table('seller_bank_accounts')->whereIn('seller_id', $sellerIds)
            ->select([
                'id','seller_id','account_type_id','account_holder_name','bank_name','account_number',
                'iban','branch_name','branch_code','mobile_number','is_primary','is_verified','verified_at',
            ])->get()->groupBy('seller_id');

        $documents = DB::table('seller_documents')->whereIn('seller_id', $sellerIds)
            ->select([
                'id','seller_id','document_type_id','status_id','document_number','file_path',
                'original_file_name','mime_type','file_size','admin_note','verified_at','verified_by',
            ])->get()
            ->map(function ($document) {
                $document->file_url = $document->file_path ? asset('storage/' . $document->file_path) : null;
                return $document;
            })->groupBy('seller_id');

        $storeSettings = DB::table('seller_store_settings')->whereIn('seller_id', $sellerIds)
        ->where('is_verified', true)
            ->select([
                'id','seller_id','store_name','store_slug','store_description','logo','banner',
                'email','phone','is_active','is_verified','verified_at',
            ])->get()
            ->map(function ($store) {
                $store->logo_url = $store->logo ? asset('storage/' . $store->logo) : null;
                $store->banner_url = $store->banner ? asset('storage/' . $store->banner) : null;
                return $store;
            })->groupBy('seller_id');

        $sellers->each(function ($seller) use ($addresses, $bankAccounts, $documents, $storeSettings)
        {
            $seller->addresses = $addresses->get($seller->seller_id, collect())->values();
            $seller->bank_accounts = $bankAccounts->get($seller->seller_id, collect())->values();
            $seller->documents = $documents->get($seller->seller_id, collect())->values();
            $seller->store_settings = $storeSettings->get($seller->seller_id, collect())->values();
        });

        return response()->json([
            'status' => true,
            'message' => 'Seller profiles fetched successfully.',
            'data' => $sellers,
        ], 200);
    }

    public function profileshowdetail(Requests $request): JsonResponse
    {

        $seller = DB::table('sellers')
            ->leftJoin('users', 'users.id', '=', 'sellers.user_id')
            ->select([
                'users.id as user_id','users.name','users.email','sellers.id as seller_id','sellers.business_name',
                'sellers.slug','sellers.business_email','sellers.business_phone','sellers.description',
                'sellers.status_id','sellers.approved_at','sellers.created_at','sellers.updated_at',
            ])->first();

        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'Seller profile not found.',
                'data' => null,
            ], 404);
        }

        $addresses = DB::table('seller_addresses')->where('seller_id', $seller->seller_id)
            ->select([
                'id','seller_id','state_id','city_id','address_line_1','address_line_2',
                'area','postal_code','country','contact_person','phone','is_primary',
            ])->get();

        $bankAccounts = DB::table('seller_bank_accounts')->where('seller_id', $seller->seller_id)
            ->select([
                'id','seller_id','account_type_id','account_holder_name','bank_name','account_number',
                'iban','branch_name','branch_code','mobile_number','is_primary','is_verified','verified_at',
            ])->get();

        $documents = DB::table('seller_documents')->where('seller_id', $seller->seller_id)
            ->select([
                'id','seller_id','document_type_id','status_id','document_number','file_path',
                'original_file_name','mime_type','file_size','admin_note','verified_at','verified_by',
            ])->get()
            ->map(function ($document) {
                $document->file_url = $document->file_path ? asset('storage/' . $document->file_path) : null;
                return $document;
            });

        $storeSettings = DB::table('seller_store_settings')->where('seller_id', $seller->seller_id)
        ->where('is_verified', true)
            ->select([
                'id','seller_id','store_name','store_slug','store_description','logo',
                'banner','email','phone','is_active','is_verified','verified_at',
            ])->get()
            ->map(function ($store) {
                $store->logo_url = $store->logo ? asset('storage/' . $store->logo) : null;
                $store->banner_url = $store->banner ? asset('storage/' . $store->banner) : null;
                return $store;
            });

        $seller->addresses = $addresses;
        $seller->bank_accounts = $bankAccounts;
        $seller->documents = $documents;
        $seller->store_settings = $storeSettings;

        return response()->json([
            'status' => true,
            'message' => 'Seller profile fetched successfully.',
            'data' => $seller,
        ], 200);
    }

    public function sellerprofileapproval(Request $request): JsonResponse
    {
        $request->validate([
            'seller_id'  => 'required|exists:sellers,id',
            'status_id'     => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $seller = Seller::where('id', $request->seller_id)->first();

        if (!$seller) {
            return response()->json([
                'status'  => false,
                'message' => 'Seller not found.',
                'data'    => null,
            ], 404);
        }

        $seller->status_id = $request->status_id;
        $seller->admin_note = $request->reason;
        $seller->save();

        DB::table('seller_store_settings')->where('seller_id', $seller->id)
            ->update([
                'is_verified' => true,
            ]);

        return response()->json([
            'status'  => true,
            'message' => 'Seller profile status updated successfully.',
            'data'    => [
                'seller_id'  => $seller->id,
                'status_id'     => $seller->status_id,
                'admin_note' => $seller->admin_note,
            ],
        ], 200);
    }

    public function profilestatscheck($id): JsonResponse
    {
        $seller = Seller::where('id', $id)->where('status_id', 36)->first();

        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'Seller not found or not approved.',
                'data' => false,
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Seller is approved.',
            'data' => true,
        ], 200);
    }


}
