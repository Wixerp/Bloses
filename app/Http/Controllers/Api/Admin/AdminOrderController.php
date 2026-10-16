<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminOrderController extends Controller
{
    protected AdminOrderService $adminOrderService;

    public function __construct(AdminOrderService $adminOrderService)
    {
        $this->adminOrderService = $adminOrderService;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'seller_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $orders = $this->adminOrderService->getOrders($validated);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }
}
