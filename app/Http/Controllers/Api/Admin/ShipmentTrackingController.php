<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ShipmentTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShipmentTrackingController extends Controller
{
    public function __construct(
        private ShipmentTrackingService $trackingService
    ) {}

    public function track(Request $request): JsonResponse
    {
        try {
            $result = $this->trackingService->track($request);

            if (!$result['found']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Shipment tracking details fetched successfully.',
                'data' => [
                    'shipment' => $result['shipment'],
                    'items' => $result['items'],
                    'timeline' => [
                        [
                            'event' => 'Shipment Created',
                            'timestamp' => $result['shipment']->created_at,
                            'completed' => true,
                        ],
                        [
                            'event' => 'Shipped',
                            'timestamp' => $result['shipment']->shipped_at,
                            'completed' => $result['shipment']->shipped_at !== null,
                        ],
                        [
                            'event' => 'Delivered',
                            'timestamp' => $result['shipment']->delivered_at,
                            'completed' => $result['shipment']->delivered_at !== null,
                        ],
                        [
                            'event' => 'Returned',
                            'timestamp' => $result['shipment']->returned_at,
                            'completed' => $result['shipment']->returned_at !== null,
                        ],
                    ],
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch shipment tracking details.',
            ], 500);
        }
    }
}
