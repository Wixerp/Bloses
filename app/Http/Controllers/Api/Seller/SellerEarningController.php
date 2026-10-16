<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerEarningService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerEarningController extends Controller
{
    public function __construct(
        private SellerEarningService $earningService
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Seller earnings summary fetched successfully.',
            'data' => $this->earningService->summary(),
        ]);
    }

    // public function index(Request $request): JsonResponse
    // {
    //     $data = $request->validate([
    //         'status' => ['nullable','in:available,paid,refunded,cancelled',],
    //         'per_page' => ['nullable','integer','min:1','max:100', ],
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Seller earnings fetched successfully.',
    //         'data' => $this->earningService->listing($data),
    //     ]);
    // }
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:available,paid,refunded,cancelled',],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller earnings fetched successfully.',
            'data' => $this->earningService->listing($filters),
        ]);
    }
}
