<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SellerSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SellerSuspensionController extends Controller
{
    public function __construct(
        private SellerSuspensionService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:150',
            'is_suspended' => 'nullable|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller suspension list fetched successfully.',
                'data' => $this->service->getAll($filters),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch seller suspension list.',
            ], 500);
        }
    }

    public function show(int $sellerId): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller details fetched successfully.',
                'data' => $this->service->getById($sellerId),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch seller details.',
            ], 500);
        }
    }

    public function suspend(Request $request, int $sellerId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller suspended successfully.',
                'data' => $this->service->suspend(
                    $sellerId,
                    $validated['reason']
                ),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to suspend seller.',
            ], 500);
        }
    }

    public function reactivate(Request $request, int $sellerId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Seller reactivated successfully.',
                'data' => $this->service->reactivate(
                    $sellerId,
                    $validated['reason'] ?? null
                ),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to reactivate seller.',
            ], 500);
        }
    }
}
