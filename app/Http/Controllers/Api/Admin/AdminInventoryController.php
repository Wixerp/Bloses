<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function __construct(
        private AdminInventoryService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:150',
            'product_id' => 'nullable|integer|min:1',
            'manage_stock' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'stock_status' => 'nullable|in:all,in_stock,low_stock,out_of_stock',
            'threshold' => 'nullable|integer|min:0',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->service->index($filters);

        return response()->json([
            'success' => true,
            'message' => 'Inventory fetched successfully.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold' => 'nullable|integer|min:0',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory summary fetched successfully.',
            'data' => $this->service->summary(
                (int) ($data['threshold'] ?? 5)
            ),
        ]);
    }

    public function adjustStock(
        Request $request,
        int $variantId
    ): JsonResponse {
        $data = $request->validate([
            'mode' => 'required|in:set,increase,decrease',
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->service->adjustStock(
            $variantId,
            $data,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'data' => $result,
        ]);
    }

    public function history(Request $request, int $variantId): JsonResponse
    {
        $data = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->service->history(
            $variantId,
            (int) ($data['per_page'] ?? 20)
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock history fetched successfully.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }
}
