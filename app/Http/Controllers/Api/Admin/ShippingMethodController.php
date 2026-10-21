<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ShippingMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShippingMethodController extends Controller
{
    public function __construct(
        private ShippingMethodService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'type_id' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping methods fetched successfully.',
            'data' => $this->service->index($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Shipping method fetched successfully.',
            'data' => $this->service->show($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:shipping_methods,code',
            'description' => 'nullable|string',
            'type_id' => 'required|integer|exists:master_types,id',
            'base_price' => 'required|numeric|min:0',
            'price_per_kg' => 'nullable|numeric|min:0',
            'estimated_min_days' => 'required|integer|min:0',
            'estimated_max_days' => 'required|integer|min:0|gte:estimated_min_days',
            'is_active' => 'sometimes|boolean',
        ]);

        $method = $this->service->store($data);

        return response()->json([
            'success' => true,
            'message' => 'Shipping method created successfully.',
            'data' => $method,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('shipping_methods', 'code')->ignore($id),
            ],
            'description' => 'sometimes|nullable|string',
            'type_id' => 'sometimes|required|integer|exists:master_types,id',
            'base_price' => 'sometimes|required|numeric|min:0',
            'price_per_kg' => 'sometimes|nullable|numeric|min:0',
            'estimated_min_days' => 'sometimes|required|integer|min:0',
            'estimated_max_days' => 'sometimes|required|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name',
            'code',
            'description',
            'type_id',
            'base_price',
            'price_per_kg',
            'estimated_min_days',
            'estimated_max_days',
            'is_active',
        ]);

        // Validate the final min/max combination, including unchanged values.
        $current = $this->service->show($id);

        $minDays = $data['estimated_min_days']
            ?? $current->estimated_min_days;

        $maxDays = $data['estimated_max_days']
            ?? $current->estimated_max_days;

        if ($maxDays < $minDays) {
            throw ValidationException::withMessages([
                'estimated_max_days' => [
                    'Maximum delivery days must be greater than or equal to minimum delivery days.',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipping method updated successfully.',
            'data' => $this->service->update($id, $data),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping method status updated successfully.',
            'data' => $this->service->updateStatus(
                $id,
                $data['is_active']
            ),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->destroy($id);

            return response()->json([
                'success' => true,
                'message' => 'Shipping method deleted successfully.',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete shipping method.',
            ], 500);
        }
    }
}
