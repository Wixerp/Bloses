<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\CloudinaryController;
use App\Http\Controllers\Api\Auth\{SocialAuthController, AuthController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Admin\{ProductsController, ReviewController, OrderController};
use App\Http\Controllers\Api\Admin\User\UserController;
use App\Http\Controllers\Api\Admin\Coupon\CouponController;
use App\Http\Controllers\Api\Support\AdminSupportController;
use App\Http\Controllers\Api\MasterType\MasterTypeController;
use App\Http\Controllers\Api\Seller\SellerController;
use App\Http\Controllers\Api\Categories\CategoriesController;

Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
Route::post('/cloudinary/upload', [CloudinaryController::class, 'upload']);

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);

    Route::middleware(['auth:sanctum', 'role:admin',])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'adminLogout']);
        Route::post('/seller-approval', [SellerController::class, 'sellerprofileapproval']);

        Route::get('/categories/{id}', [CategoriesController::class, 'getCategory']);
        Route::post('/categories', [CategoriesController::class, 'postCategories']);
        Route::put('/categories/{id}', [CategoriesController::class, 'updateCategories']);
        Route::delete('/categories/{id}', [CategoriesController::class, 'deleteCategories']);

        Route::get('/products/sellers', [ ProductsController::class, 'sellers']);
        Route::get('/products/sellers/{sellerId}/stores', [ ProductsController::class, 'sellerStores']);
        Route::get('/products/stores/{storeId}/products', [ ProductsController::class, 'storeProducts']);
        Route::get('/products', [ ProductsController::class, 'allProducts']);

        Route::post('/coupons', [CouponController::class, 'store']);
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{id}', [CouponController::class, 'show']);
        Route::put('/coupons/{id}', [CouponController::class, 'update']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}/block', [UserController::class, 'is_blocked']);

        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::get('/reviews/{id}', [ReviewController::class, 'show']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::get('/tickets', [AdminSupportController::class, 'index']);
        Route::get('/tickets/{id}', [AdminSupportController::class, 'show']);
        Route::post('/tickets/{id}/reply', [AdminSupportController::class, 'reply']);
        Route::patch('/tickets/{id}/status', [AdminSupportController::class, 'updateStatus']);
    });

});

Route::get('/categories', [CategoriesController::class, 'getCategories']);
Route::get('/master-types/{key}', [MasterTypeController::class, 'getByKey']);

Route::get('/test', function () {
    return 'api run';
});
