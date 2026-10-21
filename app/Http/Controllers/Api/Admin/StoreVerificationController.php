<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\StoreVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StoreVerificationController extends Controller
{
    public function __construct(
        protected StoreVerificationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'is_verified' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->service->getAll($filters);

        return response()->json([
            'success' => true,
            'message' => 'Store verifications retrieved successfully.',
            'data' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    public function show(int $storeId): JsonResponse
    {
        $store = $this->service->getById($storeId);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store details retrieved successfully.',
            'data' => $store,
        ]);
    }

    public function update(Request $request, int $storeId): JsonResponse
    {
        $validated = $request->validate([
            'is_verified' => ['required', 'boolean'],
        ]);

        $store = $this->service->updateVerification(
            $storeId,
            (bool) $validated['is_verified']
        );

        return response()->json([
            'success' => true,
            'message' => $validated['is_verified']
                ? 'Store verified successfully.'
                : 'Store verification removed successfully.',
            'data' => $store,
        ]);
    }
}
