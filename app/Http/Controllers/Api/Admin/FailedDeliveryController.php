<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FailedDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FailedDeliveryController extends Controller
{
    public function __construct(
        private FailedDeliveryService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'shipment_id' => 'nullable|integer|min:1',
            'order_id' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Failed deliveries fetched successfully.',
            ...$this->service->index($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Shipment details fetched successfully.',
            'data' => $this->service->show($id),
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'status_id' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        $shipment = $this->service->updateStatus(
            $id,
            (int) $data['status_id'],
            $data['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Shipment status updated successfully.',
            'data' => $shipment,
        ]);
    }
}
