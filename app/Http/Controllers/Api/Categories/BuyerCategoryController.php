<?php

namespace App\Http\Controllers\Api\Categories;

use App\Http\Controllers\Controller;
use App\Services\Categories\BuyerCategoryService;
use Illuminate\Http\JsonResponse;

class BuyerCategoryController extends Controller
{
    public function __construct(
        private BuyerCategoryService $categoryService
    ) {}

    public function departments(): JsonResponse
    {
        $data = $this->categoryService->getMenuDepartments();
        return response()->json([
            'success' => true,
            'message' => 'Departments fetched successfully.',
            'data' => $data,
        ]);
    }

    public function categories(): JsonResponse
    {
        $data = $this->categoryService->getMenuCategories();
        return response()->json([
            'success' => true,
            'message' => 'Categories fetched successfully.',
            'data' => $data,
        ]);
    }
}
