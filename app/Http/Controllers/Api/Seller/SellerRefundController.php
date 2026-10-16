<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerRefundController extends Controller
{
    public function __construct(
        private SellerRefundService $service
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Refund summary fetched successfully.',
            'data' => $this->service->summary(),
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller refunds fetched successfully.',
            'data' => $this->service->index($filters),
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Refund details fetched successfully.',
            'data' => $this->service->show($id),
        ], 200);
    }
}
