<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerPayoutController extends Controller
{
    public function __construct(
        private SellerPayoutService $payoutService
    ) {}

    public function balance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Available payout balance fetched successfully.',
            'data' => [
                'available_balance' => $this->payoutService->availableBalance(),
                'currency' => 'PKR',
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payout history fetched successfully.',
            'data' => $this->payoutService->listing(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_bank_account_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                // 'in:PKR',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $payout = $this->payoutService->requestPayout($data);

        return response()->json([
            'success' => true,
            'message' => 'Payout request submitted successfully.',
            'data' => $payout,
        ], 201);
    }
}
