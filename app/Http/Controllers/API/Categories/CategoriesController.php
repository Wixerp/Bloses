<?php

namespace App\Http\Controllers\API\Categories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Requests\Product\UpdateCategoryRequest;
use App\Http\Resources\Product\CategoryResource;
use App\Http\Resources\Product\ProductCardResource;
use App\Services\Categories\CategoriesService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class CategoriesController extends Controller
{
    public function __construct(
        protected CategoriesService $categoriesService
    ) {
    }

    public function getCategories(): JsonResponse
    {
        $categories = $this->categoriesService->getCategories();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => CategoryResource::collection(
                $categories
            ),
        ]);
    }

    public function postCategories(StoreCategoryRequest $request): JsonResponse {

        $category = $this->categoriesService
            ->createCategory(
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function getCategory(int $id): JsonResponse {
        $category = $this->categoriesService->getCategoryById($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    public function updateCategories(UpdateCategoryRequest $request, int $id): JsonResponse {

        $category = $this->categoriesService->updateCategory($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    public function deleteCategories(int $id): JsonResponse {
        $this->categoriesService->deleteCategory($id);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }

    public function categoryProducts(int $categoryId): JsonResponse
    {
        $products = $this->categoriesService->getCategoryProducts($categoryId);

        return response()->json([
            'success' => true,
            'message' => 'Category products retrieved successfully.',
            'data' => ProductCardResource::collection($products),
        ]);
    }

}
