<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProductInventoryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductInventoryReportController extends Controller
{
    public function __construct(
        private ProductInventoryReportService $reportService
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
            'category_id' => ['nullable', 'integer', 'min:1'],
            'status_id' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'stock_status' => [
                'nullable',
                'in:in_stock,low_stock,out_of_stock',
            ],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Product and inventory report retrieved successfully.',
                'data' => $this->reportService->report($request),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve product and inventory report.',
            ], 500);
        }
    }
}
