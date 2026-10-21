<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CommissionPayoutReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommissionPayoutReportController extends Controller
{
    public function __construct(
        private CommissionPayoutReportService $reportService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'seller_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],

            'commission_status' => ['nullable', 'string', 'max:50'],
            'commission_type' => ['nullable', 'string', 'max:50'],

            'payout_status_id' => ['nullable', 'integer', 'min:1'],
            'payout_number' => ['nullable', 'string', 'max:100'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $data = $this->reportService->report($request);

            return response()->json([
                'success' => true,
                'message' => 'Commission and payout report retrieved successfully.',
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Commission and payout report failed', [
                'error' => $e->getMessage(),
                'filters' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve commission and payout report.',
            ], 500);
        }
    }
}
