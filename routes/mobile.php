<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Auth\Seller\AuthController as SellerAuthController;
use App\Http\Controllers\Api\Auth\Buyer\AuthController as BuyerAuthController;
use App\Http\Controllers\Api\Master\MasterTypeController as MasterTypeController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController, InventoryController};
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\API\Product\ProductController;
use App\Http\Controllers\API\Categories\CategoriesController;
use App\Http\Controllers\API\Buyer\{WishlistController, AddressController};
use App\Http\Controllers\API\Order\OrderController;


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
            Route::get('/orders', [OrderController::class, 'sellerOrders']);
            Route::get('/orders/{orderId}', [OrderController::class, 'sellerOrderDetail']);
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::put('/inventory/{productId}', [InventoryController::class, 'update']);

            Route::get('/profile-show', [SellerController::class, 'profileshow']);
            Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);


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

            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel']);
            Route::post('/orders/returns', [OrderController::class, 'storeReturn']);

            Route::get('/profile', [BuyerAuthController::class, 'profile']);
            Route::put('/profile', [BuyerAuthController::class, 'updateProfile']);

            Route::get('/addresses', [AddressController::class, 'index']);
            Route::post('/addresses', [AddressController::class, 'store']);
            Route::put('/addresses/{id}', [AddressController::class, 'update']);
            Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

            Route::get('/wishlist', [WishlistController::class, 'index']);
            Route::post('/wishlist', [WishlistController::class, 'wishlist']);
            Route::post('/review-like', [ProductController::class, 'reviewLike']);

            Route::post('/logout', [BuyerAuthController::class,'mobileLogout']);
        });

    });

});
