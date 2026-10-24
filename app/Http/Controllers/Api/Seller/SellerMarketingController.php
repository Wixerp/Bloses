<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerMarketingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SellerMarketingController extends Controller
{
    public function __construct(
        private SellerMarketingService $service
    ) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'is_deal' => ['sometimes', 'nullable', 'boolean'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:from_date',
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function respond(callable $callback)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Request completed successfully.',
                'data' => $callback(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            if ($e->getCode() === 403 || $e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], (int) $e->getCode());
            }

            Log::error('Seller marketing API error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process marketing request.',
            ], 500);
        }
    }

    public function products(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn () => $this->service->products($request)
        );
    }

    public function updatePromotion(Request $request, int $productId)
    {
        $data = $request->validate([
            'discount' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
            ],
            'discount_percentage' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
                'lte:100',
            ],
            'is_deal' => ['sometimes', 'boolean'],
            'is_flash_sale' => ['sometimes', 'boolean'],
            'deal_start_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'deal_end_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:deal_start_at',
            ],
        ]);

        return $this->respond(
            fn () => $this->service->updatePromotion($productId, $data)
        );
    }

    public function couponUsageReport(Request $request)
    {
        $this->filters($request);

        return $this->respond(
            fn () => $this->service->couponUsageReport($request)
        );
    }
}
