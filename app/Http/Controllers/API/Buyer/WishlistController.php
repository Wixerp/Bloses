<?php

namespace App\Http\Controllers\API\Buyer;

use App\Http\Controllers\Controller;
use App\Services\Buyer\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $WishlistService
    ) {}

    public function index(): JsonResponse
    {
        $wishlist = $this->WishlistService->getWishlist();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist fetched successfully.',
            'data' => $wishlist,
        ]);
    }

    public function wishlist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required','integer','exists:products,id',],
            'product_variant_id' => ['nullable','integer','exists:product_variants,id',],
            'case' => ['required','string','in:add,remove',],
        ]);

        $wishlist = $this->WishlistService->wishlist($data);

        return response()->json([
            'success' => true,
            'message' => $data['case'] === 'add' ? 'Product added to wishlist successfully.' : 'Product removed from wishlist successfully.',
            'data' => $wishlist,
        ]);
    }

}
