<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ShippingRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminShippingRateController extends Controller
{
    public function __construct(
        private ShippingRateService $shippingRateService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->shippingRateService->getAll($request);

            return response()->json([
                'success' => true,
                'message' => 'Shipping rates fetched successfully.',
                'data' => $result['rates']->items(),
                'pagination' => [
                    'current_page' => $result['rates']->currentPage(),
                    'per_page' => $result['rates']->perPage(),
                    'total' => $result['rates']->total(),
                    'last_page' => $result['rates']->lastPage(),
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch shipping rates.',
            ], 500);
        }
    }
}
