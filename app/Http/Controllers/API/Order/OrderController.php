<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;
use App\Services\Seller\SellerEarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(): JsonResponse
    {
        $orders = $this->orderService->getMyOrders();

        return response()->json([
            'success' => true,
            'message' => 'My orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    // public function store(Request $request): JsonResponse
    // {
    //     $data = $request->validate([
    //         'payment_method_id' => ['nullable','integer',],
    //         'payment_method' => ['nullable','string','max:50',],
    //         'subtotal' => ['required','numeric','min:0',],
    //         'discount_amount' => ['nullable','numeric','min:0',],
    //         'tax' => ['nullable','numeric','min:0',],
    //         'tax_amount' => ['nullable','numeric','min:0',],
    //         'shipping_cost' => ['nullable','numeric','min:0',],
    //         'shipping_amount' => ['nullable','numeric','min:0',],
    //         'total' => ['required','numeric','min:0',],
    //         'customer_note' => ['nullable','string',],
    //         'items' => ['required','array','min:1',],
    //         'items.*.product_id' => ['required','integer','exists:products,id',],
    //         'items.*.product_variant_id' => ['nullable','integer','exists:product_variants,id',],
    //         'items.*.quantity' => ['required','integer','min:1',],
    //         'items.*.price' => ['required','numeric','min:0',],
    //         'shipping' => ['required','array',],
    //         'shipping.first_name' => ['required','string','max:255',],
    //         'shipping.last_name' => ['nullable','string','max:255',],
    //         'shipping.phone' => ['required','string','max:50',],
    //         'shipping.email' => ['nullable','email','max:255',],
    //         'shipping.address' => ['required','string','max:500',],
    //         'shipping.city' => ['required','string','max:255',],
    //         'shipping.state' => ['nullable','string','max:255',],
    //         'shipping.postal_code' => ['nullable','string','max:50',],
    //         'shipping.country' => ['required','string','max:255',],
    //     ]);

    //     $data['address'] = [
    //         'first_name' => $data['shipping']['first_name'],
    //         'last_name' => $data['shipping']['last_name'] ?? null,
    //         'phone' => $data['shipping']['phone'],
    //         'email' => $data['shipping']['email'] ?? null,
    //         'address_line_1' => $data['shipping']['address'],
    //         'address_line_2' => null,
    //         'city' => $data['shipping']['city'],
    //         'state' => $data['shipping']['state'] ?? null,
    //         'postal_code' => $data['shipping']['postal_code'] ?? null,
    //         'country' => $data['shipping']['country'],
    //         'delivery_instructions' => null,
    //     ];

    //     if (isset($data['tax']) && !isset($data['tax_amount'])) {
    //         $data['tax_amount'] = $data['tax'];
    //     }

    //     if (isset($data['shipping_cost']) && !isset($data['shipping_amount'])) {
    //         $data['shipping_amount'] = $data['shipping_cost'];
    //     }

    //     $order = $this->orderService->createOrder($data);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Order created successfully.',
    //         'data' => $order,
    //     ], 201);
    // }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],

            // Coupon is optional.
            'coupon_code' => ['nullable', 'string', 'max:100'],

            // Backend calculates subtotal, discount and total.
            'tax' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],

            'customer_note' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            // Do not accept item price from the buyer.
            // Price is retrieved from the database.

            'shipping' => ['required', 'array'],
            'shipping.first_name' => ['required', 'string', 'max:255'],
            'shipping.last_name' => ['nullable', 'string', 'max:255'],
            'shipping.phone' => ['required', 'string', 'max:50'],
            'shipping.email' => ['nullable', 'email', 'max:255'],
            'shipping.address' => ['required', 'string', 'max:500'],
            'shipping.city' => ['required', 'string', 'max:255'],
            'shipping.state' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['nullable', 'string', 'max:50'],
            'shipping.country' => ['required', 'string', 'max:255'],
        ]);

        $shipping = $data['shipping'];

        $data['address'] = [
            'first_name' => $shipping['first_name'],
            'last_name' => $shipping['last_name'] ?? null,
            'phone' => $shipping['phone'],
            'email' => $shipping['email'] ?? null,
            'address_line_1' => $shipping['address'],
            'address_line_2' => null,
            'city' => $shipping['city'],
            'state' => $shipping['state'] ?? null,
            'postal_code' => $shipping['postal_code'] ?? null,
            'country' => $shipping['country'],
            'delivery_instructions' => null,
        ];

        $order = $this->orderService->createOrder($data);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }

    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $order = $this->orderService->cancelOrder($orderId, $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $order,
        ]);
    }

    public function storeReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required','integer','exists:orders,id',],
            'reason' => ['required','string','max:1000',],
            'issue' => ['required','string','max:1000',],
        ]);

        $return = $this->orderService->createReturn($data);

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted successfully.',
            'data' => $return,
        ], 201);
    }

    public function sellerOrders(): JsonResponse
    {
        $orders = $this->orderService->sellerOrders();

        return response()->json([
            'success' => true,
            'message' => 'Seller orders fetched successfully.',
            'data' => $orders,
        ]);
    }

    public function sellerOrderDetail(int $orderId): JsonResponse
    {
        $order = $this->orderService->sellerOrderDetail($orderId);

        return response()->json([
            'success' => true,
            'message' => 'Seller order detail fetched successfully.',
            'data' => $order,
        ]);
    }

    public function updateOrderStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'status_id' => ['required', 'integer', 'exists:master_types,id'],
        ]);

        $order = $this->orderService->updateOrderStatus(
            (int) $data['order_id'],
            (int) $data['status_id']
        );
         app(SellerEarningService::class)
            ->generateForDeliveredOrder(
                (int) $data['order_id']
            );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => $order,
        ]);

    }

    public function sellerReturnOrders(): JsonResponse
    {
        $returns = $this->orderService->sellerReturnOrders();

        return response()->json([
            'success' => true,
            'message' => 'Seller return orders fetched successfully.',
            'data' => $returns,
        ]);
    }




}
