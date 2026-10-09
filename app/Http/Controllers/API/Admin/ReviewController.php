<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    /**
     * Reviews listing for Admin.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_approved' => ['nullable', 'in:true,false,null'],
        ]);

        $reviews = $this->reviewService->getReviews(
            $request->only(['search', 'is_approved'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Reviews fetched successfully.',
            'data' => $reviews,
        ]);
    }

    /**
     * Review detail for View button.
     */
    public function show(int $id)
    {
        $review = $this->reviewService->getReviewById($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review detail fetched successfully.',
            'data' => $review,
        ]);
    }
}
