<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\Buyer\AuthController;
use App\Http\Controllers\API\Product\ProductController;
use App\Http\Controllers\API\Categories\CategoriesController;
use App\Http\Controllers\API\Admin\ProductsController;
use App\Http\Controllers\API\Order\OrderController;
use App\Http\Controllers\API\Buyer\AddressController;

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

        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        Route::post('/logout', [AuthController::class, 'logout']);
    });


    Route::get('/products/{productId}/reviews', [ProductController::class, 'getReviews']);
    Route::post('/products/{productId}/reviews', [ProductController::class, 'storeReview']);
    Route::get('/categories/{categoryId}/products', [CategoriesController::class, 'categoryProducts']);
    Route::get('/products/{productId}', [ProductController::class, 'getProduct']);
    Route::post('/products/{productId}/questions', [ProductController::class, 'storeQuestions']);
    Route::get('/products', [ProductController::class, 'buyerProducts']);

});
