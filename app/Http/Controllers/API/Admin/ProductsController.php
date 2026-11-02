<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductFilterRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Services\Admin\ProductService;
use Illuminate\Http\JsonResponse;

class ProductsController extends Controller
{
     public function __construct(
        protected ProductService $productService
    ) {
    }


    /**
     * Seller & Stores → Sellers
     */
    public function sellers(): JsonResponse
    {
        $sellers = $this->productService->getSellers();

        return response()->json([
            'status' => true,
            'message' => 'Sellers retrieved successfully.',
            'data' => $sellers,
        ]);
    }


    /**
     * Seller → Stores
     */
    public function sellerStores(int $sellerId): JsonResponse
    {
        $stores = $this->productService->getSellerStores($sellerId);

        return response()->json([
            'status' => true,
            'message' => 'Seller stores retrieved successfully.',
            'data' => $stores,
        ]);
    }


    /**
     * Store → Products
     */
    public function storeProducts(
        ProductFilterRequest $request,
        int $storeId
    ): JsonResponse {
        $products = $this->productService->getStoreProducts(
            $storeId,
            $request->validated()
        );

        return response()->json([
            'status' => true,
            'message' => 'Store products retrieved successfully.',
            'data' => ProductResource::collection($products),
        ]);
    }


    /**
     * All Products
     */
    public function allProducts(
        ProductFilterRequest $request
    ): JsonResponse {
        $products = $this->productService->getAllProducts(
            $request->validated()
        );

        return response()->json([
            'status' => true,
            'message' => 'All products retrieved successfully.',
            'data' => ProductResource::collection($products),
        ]);
    }

    public function filter()
    {
        $productfilter = DB::table('products')->select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $productfilter,
        ]);
    }

}
