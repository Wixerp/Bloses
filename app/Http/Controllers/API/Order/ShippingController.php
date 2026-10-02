<?php

namespace App\Http\Controllers\API\Order;

use App\Services\Order\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }


    public function shippingMethods(): JsonResponse
    {
        $methods = $this->shippingService->shippingMethods();

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }

}
