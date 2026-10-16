<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminEarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEarningController extends Controller
{
    public function __construct(
        private AdminEarningService $earningService
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Earnings summary fetched successfully.',
            'data' => $this->earningService->summary(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                'in:available,paid,refunded,cancelled',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller earnings fetched successfully.',
            'data' => $this->earningService->listing($filters),
        ]);
    }
}
