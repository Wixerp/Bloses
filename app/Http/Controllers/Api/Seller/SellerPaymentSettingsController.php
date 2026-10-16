<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerPaymentSettingsService;
use Illuminate\Http\JsonResponse;

class SellerPaymentSettingsController extends Controller
{
    public function __construct(
        private SellerPaymentSettingsService $service
    ) {}

    /**
     * GET /api/seller/payment-settings
     */
    public function index(): JsonResponse
    {
        $data = $this->service->index();

        return response()->json([
            'success' => true,
            'message' => 'Seller payment settings fetched successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/seller/payment-settings/methods
     */
    public function methods(): JsonResponse
    {
        $data = $this->service->methods();

        return response()->json([
            'success' => true,
            'message' => 'Available payment methods fetched successfully.',
            'data' => $data,
        ], 200);
    }
}
