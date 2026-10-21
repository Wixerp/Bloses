<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    public function __construct(
        protected AdminSellerService $sellerService
    ) {}

    /**
     * GET /api/admin/sellers
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer', 'min:1'],
            'is_verified' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sellers = $this->sellerService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sellers retrieved successfully.',
            'data' => $sellers->items(),
            'pagination' => [
                'current_page' => $sellers->currentPage(),
                'per_page' => $sellers->perPage(),
                'total' => $sellers->total(),
                'last_page' => $sellers->lastPage(),
                'from' => $sellers->firstItem(),
                'to' => $sellers->lastItem(),
            ],
        ], 200);
    }

    /**
     * GET /api/admin/sellers/{sellerId}
     */
    public function show(int $sellerId): JsonResponse
    {
        $seller = $this->sellerService->getById($sellerId);

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Seller not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Seller details retrieved successfully.',
            'data' => $seller,
        ], 200);
    }
}
