<?php

namespace App\Http\Controllers\API\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }


    /**
     * Inventory List
     */
    public function index(): JsonResponse
    {
        $inventory = $this->inventoryService->index();

        return response()->json([
            'success' => true,
            'message' => 'Seller inventory fetched successfully.',
            'data' => $inventory,
        ]);
    }

    public function update(Request $request, int $productId): JsonResponse {

        $data = $request->validate([
            'qty' => ['required','integer','min:0',],
            'variant_id' => ['nullable','integer','exists:product_variants,id',],
        ]);

        $inventory = $this->inventoryService->update(
            $productId,
            (int) $data['qty'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Inventory updated successfully.',
            'data' => $inventory,
        ]);
    }
}
