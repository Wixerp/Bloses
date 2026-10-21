<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminShipmentController extends Controller
{
    public function __construct(
        private AdminShipmentService $shipmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->shipmentService->getAll($request);

            return response()->json([
                'success' => true,
                'message' => 'All shipments fetched successfully.',
                'data' => $result['shipments'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch shipments.',
            ], 500);
        }
    }
}
