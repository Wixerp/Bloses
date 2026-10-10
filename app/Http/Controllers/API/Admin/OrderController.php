<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Admin orders listing.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer'],
        ]);

        $orders = $this->orderService->getOrders(
            $request->only(['search', 'status_id'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Orders fetched successfully.',
            'data' => $orders,
        ]);
    }

    /**
     * View complete order details.
     */
    public function show(int $id)
    {
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order details fetched successfully.',
            'data' => $order,
        ]);
    }
}
