<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CustomerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerReportController extends Controller
{
    public function __construct(
        private CustomerReportService $customerReportService
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
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'is_block' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Customer report retrieved successfully.',
                'data' => $this->customerReportService->report($request),
            ]);
        } catch (Throwable $e) {
            Log::error('Customer report failed', [
                'error' => $e->getMessage(),
                'filters' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve customer report.',
            ], 500);
        }
    }
}
