<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ShippingConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ShippingConfigurationController extends Controller
{
    public function __construct(
        private ShippingConfigurationService $service
    ) {}

    private function success($data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function failure(Throwable $e): JsonResponse
    {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Unable to process shipping configuration.',
        ], 500);
    }

    public function index(): JsonResponse
    {
        try {
            return $this->success(
                $this->service->index(),
                'Shipping configuration retrieved.'
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // Shipping Methods

    public function methods(): JsonResponse
    {
        try {
            return $this->success($this->service->methods());
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function storeMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:shipping_methods,code',
            'description' => 'nullable|string',
            'type_id' => 'nullable|integer|exists:master_types,id',
            'base_price' => 'nullable|numeric|min:0',
            'price_per_kg' => 'nullable|numeric|min:0',
            'estimated_min_days' => 'nullable|integer|min:0',
            'estimated_max_days' => 'nullable|integer|min:0|gte:estimated_min_days',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success(
            $this->service->storeMethod($data),
            'Shipping method created.',
            201
        );
    }

    public function updateMethod(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('shipping_methods', 'code')->ignore($id),
            ],
            'description' => 'sometimes|nullable|string',
            'type_id' => 'sometimes|nullable|integer|exists:master_types,id',
            'base_price' => 'sometimes|numeric|min:0',
            'price_per_kg' => 'sometimes|numeric|min:0',
            'estimated_min_days' => 'sometimes|nullable|integer|min:0',
            'estimated_max_days' => 'sometimes|nullable|integer|min:0|gte:estimated_min_days',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success(
            $this->service->updateMethod($id, $data),
            'Shipping method updated.'
        );
    }

    public function destroyMethod(int $id): JsonResponse
    {
        $this->service->deleteMethod($id);

        return $this->success(null, 'Shipping method deleted.');
    }

    // Shipping Zones

    public function zones(): JsonResponse
    {
        try {
            return $this->success($this->service->zones());
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function storeZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:shipping_zones,code',
            'description' => 'nullable|string',
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'is_nationwide' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $this->validateZoneScope($data);

        return $this->success(
            $this->service->storeZone($data),
            'Shipping zone created.',
            201
        );
    }

    public function updateZone(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('shipping_zones', 'code')->ignore($id),
            ],
            'description' => 'sometimes|nullable|string',
            'state_id' => 'sometimes|nullable|integer|exists:states,id',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'is_nationwide' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $current = (array) \Illuminate\Support\Facades\DB::table('shipping_zones')
            ->where('id', $id)->first();

        if (!$current) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping zone not found.',
            ], 404);
        }

        $data = $this->validateZoneScope(array_merge($current, $data));

        // Update only fields supplied in the request.
        $data = array_intersect_key(
            $data,
            array_flip([
                'name', 'code', 'description', 'state_id',
                'city_id', 'is_nationwide', 'is_active',
            ])
        );

        return $this->success(
            $this->service->updateZone($id, $data),
            'Shipping zone updated.'
        );
    }

    private function validateZoneScope(array $data): array
    {
        $nationwide = (bool) ($data['is_nationwide'] ?? false);
        $stateId = $data['state_id'] ?? null;
        $cityId = $data['city_id'] ?? null;

        if ($nationwide && ($stateId || $cityId)) {
            abort(422, 'Nationwide zones cannot have a specific state or city.');
        }

        if ($cityId && !$stateId) {
            abort(422, 'A state is required when a city is selected.');
        }

        if ($cityId && $stateId) {
            $cityBelongsToState = \Illuminate\Support\Facades\DB::table('cities')
                ->where('id', $cityId)
                ->where('state_id', $stateId)
                ->exists();

            if (!$cityBelongsToState) {
                abort(422, 'The selected city does not belong to the selected state.');
            }
        }

        return $data;
    }

    public function destroyZone(int $id): JsonResponse
    {
        $this->service->deleteZone($id);

        return $this->success(null, 'Shipping zone deleted.');
    }

    // Shipping Rates

    public function rates(): JsonResponse
    {
        try {
            return $this->success($this->service->rates());
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'shipping_zone_id' => 'required|integer|exists:shipping_zones,id',
            'base_rate' => 'required|numeric|min:0',
            'per_kg_rate' => 'nullable|numeric|min:0',
            'free_shipping_min_amount' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|gt:0',
            'currency' => 'sometimes|required|string|size:3',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success(
            $this->service->storeRate($data),
            'Shipping rate created.',
            201
        );
    }

    public function updateRate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'shipping_method_id' => 'sometimes|required|integer|exists:shipping_methods,id',
            'shipping_zone_id' => 'sometimes|required|integer|exists:shipping_zones,id',
            'base_rate' => 'sometimes|required|numeric|min:0',
            'per_kg_rate' => 'sometimes|numeric|min:0',
            'free_shipping_min_amount' => 'sometimes|nullable|numeric|min:0',
            'max_weight' => 'sometimes|nullable|numeric|gt:0',
            'currency' => 'sometimes|required|string|size:3',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success(
            $this->service->updateRate($id, $data),
            'Shipping rate updated.'
        );
    }

    public function destroyRate(int $id): JsonResponse
    {
        $this->service->deleteRate($id);

        return $this->success(null, 'Shipping rate deleted.');
    }
}
