<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\Buyer\AuthController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Categories\CategoriesController;
use App\Http\Controllers\Api\Admin\ProductsController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Buyer\AddressController;
use App\Http\Controllers\Api\Buyer\WishlistController;
use App\Http\Controllers\Api\Support\BuyerSellerChatController;


Route::prefix('buyer')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:web', 'role:buyer'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        //Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{orderId}', [OrderController::class, 'show']);
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/returns', [OrderController::class, 'storeReturn']);

        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'wishlist']);
        Route::post('/review-like', [ProductController::class, 'reviewLike']);
        Route::get('/orders/{orderId}/chat', [BuyerSellerChatController::class, 'openBuyerChat']);
        Route::post('/orders/{orderId}/chat/messages', [BuyerSellerChatController::class, 'buyerSendMessage']);

        Route::post('/logout', [AuthController::class, 'logout']);
    });


    Route::get('/products/{productId}/reviews', [ProductController::class, 'getReviews']);
    Route::post('/products/{productId}/reviews', [ProductController::class, 'storeReview']);
    Route::get('/categories/{categoryId}/products', [CategoriesController::class, 'categoryProducts']);
    Route::get('/products/{productId}', [ProductController::class, 'getProduct']);
    Route::post('/products/{productId}/questions', [ProductController::class, 'storeQuestions']);
    Route::get('/products', [ProductController::class, 'buyerProducts']);

});
