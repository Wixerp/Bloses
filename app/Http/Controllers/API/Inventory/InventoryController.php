<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(): JsonResponse
    {
        $inventory = $this->inventoryService->index();

        return response()->json([
            'success' => true,
            'message' => 'Seller inventory fetched successfully.',
            'data' => $inventory,
        ]);
    }


    public function update(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $inventory = $this->inventoryService->update(
            $productId,
            array_key_exists('qty', $data) ? $data['qty'] : null,
            array_key_exists('variant_id', $data) ? $data['variant_id'] : null,
            array_key_exists('supplier_id', $data) ? $data['supplier_id'] : null,
            array_key_exists('qty', $data),
            array_key_exists('variant_id', $data),
            array_key_exists('supplier_id', $data),
        );

        return response()->json([
            'success' => true,
            'message' => 'Inventory updated successfully.',
            'data' => $inventory,
        ]);
    }

}
