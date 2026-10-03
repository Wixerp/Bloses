<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    /**
     * Get seller shipments
     */
    public function getShipments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable','integer','exists:shipments,id',],
            'order_id' => ['nullable','integer','exists:orders,id',],
            'status_id' => ['nullable','integer','exists:master_types,id',],
            'search' => ['nullable','string','max:255',],
            'per_page' => ['nullable','integer','min:1','max:100',],
        ]);

        $shipments = $this->shipmentService->getShipments($data);

        return response()->json([
            'success' => true,
            'message' => 'Shipments fetched successfully.',
            'data' => $shipments,
        ]);
    }

    public function createShipment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required','integer','exists:orders,id',],
            'shipping_method_id' => ['required','integer','exists:master_types,id',],
            'tracking_number' => ['nullable','string','max:255',],
            'estimated_delivery_at' => ['nullable','date',],
            'notes' => ['nullable','string','max:2000',],
        ]);

        $shipment = $this->shipmentService->createShipment($data);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully.',
            'data' => $shipment,
        ], 201);
    }
}