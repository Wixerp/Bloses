<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerTransactionController extends Controller
{
    public function __construct(
        private SellerTransactionService $service
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Transaction summary fetched successfully.',
            'data' => $this->service->summary(),
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status_id' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transactions fetched successfully.',
            'data' => $this->service->index($filters),
        ], 200);
    }
}
