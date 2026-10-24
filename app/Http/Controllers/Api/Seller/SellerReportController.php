<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SellerReportController extends Controller
{
    public function __construct(
        private SellerReportService $service
    ) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status_id' => 'sometimes|integer|min:1',
            'low_stock_only' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
        ]);
    }

    private function respond(callable $callback)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller report retrieved successfully.',
                'data' => $callback(),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Seller report API failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve seller report.',
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->summary($request)
        );
    }

    public function salesByDate(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->salesByDate($request)
        );
    }

    public function orders(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->orders($request)
        );
    }

    public function bestSellingProducts(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->bestSellingProducts($request)
        );
    }

    public function inventory(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->inventory($request)
        );
    }

    public function orderStatusSummary(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn() => $this->service->orderStatusSummary($request)
        );
    }
}
