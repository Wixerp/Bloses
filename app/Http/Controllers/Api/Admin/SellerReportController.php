<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SellerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerReportController extends Controller
{
    public function __construct(
        private SellerReportService $sellerReportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'is_verified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller report retrieved successfully.',
                'data' => $this->sellerReportService->report($request),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Seller Report Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve seller report.',
            ], 500);
        }
    }
}
