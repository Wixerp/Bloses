<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminReturnController extends Controller
{
    public function __construct(
        private AdminReturnService $returnService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'return_id' => 'nullable|integer',
            'order_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'seller_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return requests retrieved successfully.',
            'data' => $this->returnService->getReturns($filters),
        ]);
    }

    public function show(int $returnId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Return details retrieved successfully.',
            'data' => $this->returnService->getReturnDetails($returnId),
        ]);
    }

    public function updateStatus(
        Request $request,
        int $returnId
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                'in:return_approved,return_rejected,pickup_scheduled,picked_up,in_transit,received,inspection_quality_check,return_accepted,return_failed,refund_initiated,refunded,return_cancelled',
            ],
            'admin_note' => 'nullable|string|max:2000',
        ]);

        if (
            $data['status'] === 'return_rejected'
            && blank($data['admin_note'] ?? null)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Admin note is required when rejecting a return.',
            ], 422);
        }

        $result = $this->returnService->updateStatus(
            $returnId,
            $data['status'],
            $data['admin_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Return status updated successfully.',
            'data' => $result,
        ]);
    }

    public function cancelOrder(int $orderId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $this->returnService->cancelOrder($orderId),
        ]);
    }
}
