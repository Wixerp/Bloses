<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerDashboardService;

class SellerDashboardController extends Controller
{
    public function __construct(
        protected SellerDashboardService $dashboardService
    ) {}

    public function index()
    {
        $data = $this->dashboardService->getDashboard();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
