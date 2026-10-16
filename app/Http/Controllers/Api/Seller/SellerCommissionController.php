<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerCommissionController extends Controller
{
    public function __construct(
        private SellerCommissionService $service
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Commission summary fetched successfully.',
            'data' => $this->service->summary(),
        ]);
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
            'message' => 'Commission details fetched successfully.',
            'data' => $this->service->index($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Commission details fetched successfully.',
            'data' => $this->service->show($id),
        ]);
    }
}
