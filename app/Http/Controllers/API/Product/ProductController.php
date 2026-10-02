<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\{StoreProductRequest, UpdateProductRequest};
use App\Models\Product;
use App\Http\Requests\Product\StoreReviewRequest;
use App\Http\Requests\Product\StoreQuestionRequest;
use App\Http\Resources\Product\ProductDetailResource;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $products = $this->productService->getAll($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        // Log::info('REQUEST FILES', [
        //     'files' => $request->allFiles(),
        // ]);

        // Log::info('REQUEST DATA', [
        //     'data' => $request->all(),
        // ]);
        $product = $this->productService->create(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productService->findById($id);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data' => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse {
        $updatedProduct = $this->productService->update(
            $product,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $updatedProduct,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function uploadImages(Request $request, int $productId): JsonResponse {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ]);

        $images = $this->productService->uploadImages(
            $productId,
            $request->file('images')
        );

        return response()->json([
            'success' => true,
            'message' => 'Product images uploaded successfully.',
            'data' => $images,
        ], Response::HTTP_CREATED);
    }

    public function deleteImage(int $productId, int $imageId): JsonResponse {
        $this->productService->deleteImage(
            $productId,
            $imageId
        );

        return response()->json([
            'success' => true,
            'message' => 'Product image deleted successfully.',
        ]);
    }

    public function getCategories(): JsonResponse
    {
        $categories = $this->productService->getCategories();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    public function getBrands(): JsonResponse
    {
        $brands = $this->productService->getBrands();

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully.',
            'data' => $brands,
        ]);
    }

    public function getProduct(int $productId): JsonResponse
    {
        $product = $this->productService->getProductDetail($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product details retrieved successfully.',
            'data' => new ProductDetailResource($product),
        ]);
    }

    public function storeReview(StoreReviewRequest $request, int $productId): JsonResponse {

        $review = $this->productService->storeReview(
            $productId,
            $request->validated(),
            $request->file('images', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'data' => [
                'id' => $review->id,
                'product_id' => $review->product_id,
                'name' => $review->title,
                'rating' => (int) $review->rating,
                'review' => $review->review,
                'is_verified_purchase' => (bool) $review->is_verified_purchase,
                'is_approved' => (bool) $review->is_approved,
                'created_at' => $review->created_at,
            ],
        ], 201);
    }

    public function storeQuestions(StoreQuestionRequest $request, int $productId): JsonResponse {

        $question = $this->productService->storeQuestion(
            $productId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Question submitted successfully. It will be visible after approval.',
            'data' => [
                'id' => $question->id,
                'product_id' => $question->product_id,
                'user_id' => $question->user_id,
                'seller_id' => $question->seller_id,
                'question' => $question->question,
                'answer' => $question->answer,
                'answered_at' => $question->answered_at,
                'is_approved' => (bool) $question->is_approved,
                'created_at' => $question->created_at,
            ],
        ], 201);
    }

    public function getQuestion(): JsonResponse
    {
        $questions = $this->productService->getSellerQuestions();

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully.',
            'data' => $questions,
        ]);
    }

    public function questionOfanswer(Request $request, int $questionId): JsonResponse {

        $request->validate([
            'answer' => ['required', 'string', 'max:5000',],
        ]);

        $question = $this->productService->answerQuestion(
            $questionId,
            $request->input('answer')
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer submitted successfully.',
            'data' => $question,
        ]);
    }

    public function getreviews(): JsonResponse
    {
        $reviews = $this->productService->getSellerReviews();

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully.',
            'data' => $reviews,
        ]);
    }

    public function reviewsOfanswer(Request $request, int $reviewid): JsonResponse {

        $request->validate([
            'reply' => [ 'required', 'string', 'max:5000', ],
        ]);

        $review = $this->productService->answerReview(
            $reviewid,
            $request->input('reply')
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer submitted successfully.',
            'data' => $review,
        ]);
    }

    public function buyerProducts(): JsonResponse
    {
        $products = $this->productService->getBuyerProducts();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function reviewLike(Request $request): JsonResponse
    {
        $data = $request->validate([
            'review_id' => ['required','integer','exists:reviews,id',],
            'case' => ['required','string','in:like,unlike',],
        ]);

        $result = $this->productService->reviewLike($data);

        return response()->json([
            'success' => true,
            'message' => $data['case'] === 'like' ? 'Review liked successfully.' : 'Review unliked successfully.',
            'data' => $result,
        ]);
    }

}
