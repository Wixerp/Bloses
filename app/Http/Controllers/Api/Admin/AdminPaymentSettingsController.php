<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPaymentSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPaymentSettingsController extends Controller
{
    public function __construct(
        private AdminPaymentSettingsService $service
    ) {}

    /**
     * GET /api/admin/payment-settings
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment settings fetched successfully.',
            'data' => $this->service->index(),
        ]);
    }

    /**
     * PUT /api/admin/payment-settings
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.currency' => [
                'sometimes', 'required', 'string', 'size:3',
            ],
            'settings.cod_enabled' => [
                'sometimes', 'required', 'boolean',
            ],
            'settings.online_payment_enabled' => [
                'sometimes', 'required', 'boolean',
            ],
            'settings.cod_minimum_amount' => [
                'sometimes', 'required', 'numeric', 'min:0',
            ],
            'settings.cod_maximum_amount' => [
                'sometimes', 'required', 'numeric', 'min:0',
            ],
        ]);

        $data = $this->service->update($validated['settings']);

        return response()->json([
            'success' => true,
            'message' => 'Payment settings updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * PATCH /api/admin/payment-settings/{key}
     */
    public function updateOne(Request $request, string $key): JsonResponse
    {
        $allowedKeys = [
            'currency',
            'cod_enabled',
            'online_payment_enabled',
            'cod_minimum_amount',
            'cod_maximum_amount',
        ];

        abort_unless(in_array($key, $allowedKeys, true), 404);

        $rules = match ($key) {
            'currency' => ['required', 'string', 'size:3'],
            'cod_enabled',
            'online_payment_enabled' => ['required', 'boolean'],
            'cod_minimum_amount',
            'cod_maximum_amount' => ['required', 'numeric', 'min:0'],
            default => ['required'],
        };

        $validated = $request->validate([
            'value' => $rules,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment setting updated successfully.',
            'data' => $this->service->updateOne(
                $key,
                $validated['value']
            ),
        ]);
    }

    /**
     * GET /api/admin/payment-settings/methods
     */
    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment methods fetched successfully.',
            'data' => $this->service->methods(),
        ]);
    }

    /**
     * PATCH /api/admin/payment-settings/methods/{id}
     */
    public function updateMethod(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully.',
            'data' => $this->service->updateMethod(
                $id,
                $validated['is_active']
            ),
        ]);
    }
}
