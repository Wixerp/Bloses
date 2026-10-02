<?php

namespace App\Http\Controllers\API\Order;

use App\Services\Order\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{

    protected ShipmentService $shipmentService;

    public function __construct(ShipmentService $shipmentService)
    {
        $this->shipmentService = $shipmentService;
    }

    public function createShipment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required','integer','exists:orders,id',],
            'shipping_method_id' => ['required','integer','exists:shipping_methods,id',],
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
