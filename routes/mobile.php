<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Auth\Seller\AuthController as SellerAuthController;
use App\Http\Controllers\Api\Auth\Buyer\AuthController as BuyerAuthController;
use App\Http\Controllers\Api\Master\MasterTypeController as MasterTypeController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\API\Product\ProductController;
use App\Http\Controllers\API\Categories\CategoriesController;

use App\Http\Controllers\API\Order\OrderController;
use App\Http\Controllers\API\Buyer\AddressController;

Route::prefix('mobile')->group(function () {

    Route::prefix('seller')->group(function () {
        Route::post('/register', [SellerAuthController::class, 'mobileRegister']);
        Route::post('/login', [SellerAuthController::class,'mobileLogin']);

        Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
            Route::get('/me', [SellerAuthController::class,'mobileMe']);

            Route::get('/cities', [SellercityController::class, 'getCitiesBycity']);
            Route::get('/provinces', [SellerProvinceController::class, 'getStateBystate']);
            Route::get('/countries', [SellerCountryController::class, 'getCountryBycountry']);
            Route::get('/master-types/{key}', [MasterTypeController::class, 'getkeBymaster']);

            Route::post('/logout', [SellerAuthController::class,'mobileLogout']);
        });
    });

    Route::prefix('buyer')->group(function () {
        Route::post('/register', [BuyerAuthController::class, 'mobileRegister']);
        Route::post('/login', [BuyerAuthController::class,'mobileLogin']);

        Route::middleware(['auth:sanctum', 'role:buyer'])->group(function () {
            Route::get('/me', [BuyerAuthController::class,'mobileMe']);

            Route::post('/products/{productId}/reviews', [ProductController::class, 'storeReview']);
            Route::post('/products/{productId}/questions', [ProductController::class, 'storeQuestions']);
            Route::get('/products', [ProductController::class, 'buyerProducts']);

            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel']);

            Route::get('/profile', [BuyerAuthController::class, 'profile']);
            Route::put('/profile', [BuyerAuthController::class, 'updateProfile']);

            Route::get('/addresses', [AddressController::class, 'index']);
            Route::post('/addresses', [AddressController::class, 'store']);
            Route::put('/addresses/{id}', [AddressController::class, 'update']);
            Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

            Route::post('/logout', [BuyerAuthController::class,'mobileLogout']);
        });

    });

});
