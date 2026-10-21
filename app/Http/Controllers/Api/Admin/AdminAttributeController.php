<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAttributeController extends Controller
{
    public function __construct(
        private AdminAttributeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->index($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->show($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute created successfully.',
            'data' => $this->service->store($data),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute updated successfully.',
            'data' => $this->service->update($id, $data),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->destroy($id),
        ]);
    }

    public function storeValue(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'color_code' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute value created successfully.',
            'data' => $this->service->storeValue($id, $data),
        ], 201);
    }

    public function updateValue(Request $request, int $id, int $valueId): JsonResponse
    {
        $data = $request->validate([
            'value' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'color_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attribute value updated successfully.',
            'data' => $this->service->updateValue($id, $valueId, $data),
        ]);
    }

    public function destroyValue(int $id, int $valueId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->destroyValue($id, $valueId),
        ]);
    }

    public function variants(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->variants($filters),
        ]);
    }

    public function variantAttributes(int $variantId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->variantAttributes($variantId),
        ]);
    }

    public function syncVariantAttributes(
        Request $request,
        int $variantId
    ): JsonResponse {
        $data = $request->validate([
            'attributes' => 'present|array',

            'attributes.*.attribute_id' =>
                'required|integer|distinct',

            'attributes.*.attribute_value_id' =>
                'required|integer',
        ]);

        $result = $this->service->syncVariantAttributes(
            $variantId,
            $data['attributes']
        );

        return response()->json([
            'success' => true,
            'message' => 'Variant attributes updated successfully.',
            'data' => $result,
        ]);
    }

}
