<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboardService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'months' => [
                'sometimes',
                'integer',
                Rule::in([3, 6, 12]),
            ],
        ]);

        try {
            $data = $this->dashboardService->getDashboard(
                (int) ($validated['months'] ?? 6)
            );

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched successfully.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data.',
            ], 500);
        }
    }
}
