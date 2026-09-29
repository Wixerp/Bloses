<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerRegistrationRequest;
use App\Services\Seller\SellerRegistrationService;
use Illuminate\Http\JsonResponse;

class SellerRegistrationController extends Controller
{
    public function __construct(
        protected SellerRegistrationService $sellerRegistrationService
    ) {
    }

    public function register(SellerRegistrationRequest $request): JsonResponse
    {
        // \Log::info('Seller registration request received', [
        //     'request_data' => $request->validated(),
        //     'files' => $request->allFiles(),
        // ]);
        $seller = $this->sellerRegistrationService->register(
            $request->validated(),
            $request->allFiles()
        );

        return response()->json([
            'status' => true,
            'message' => 'Seller registration submitted successfully.',
            'data' => $seller,
        ], 201);
    }
}
