<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Auth\Seller\AuthController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController, SellerEarningController};
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Master\MasterTypeController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Support\SupportController;
use App\Http\Controllers\Api\Seller\SellerDashboardController;


Route::prefix('seller')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/profile-status', [SellerController::class, 'registrationStatus']);
    });

});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('seller')->group(function () {
        Route::post('/profile', [SellerRegistrationController::class, 'register']);
        Route::get('/cities', [SellercityController::class, 'getCitiesBycity']);
        Route::get('/provinces', [SellerProvinceController::class, 'getStateBystate']);
        Route::get('/countries', [SellerCountryController::class, 'getcountryBycountry']);
        Route::get('/brands', [ProductController::class, 'getBrands']);
        Route::get('/master-types/{key}', [MasterTypeController::class, 'getkeBymaster']);
        Route::get('/get-questions', [ProductController::class, 'getQuestion']);
        Route::post('/questions/{questionId}/answer', [ProductController::class, 'questionOfanswer']);
        Route::get('/get-reviews', [ProductController::class, 'getReviews']);
        Route::post('/reviews/{reviewid}/answer', [ProductController::class, 'reviewsOfanswer']);
        Route::get('/orders', [OrderController::class, 'sellerOrders']);
        Route::get('/orders/returns', [OrderController::class, 'sellerReturnOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'sellerOrderDetail']);
        Route::put('/order-status-update', [OrderController::class, 'updateOrderStatus']);
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::put('/inventory/{productId}', [InventoryController::class, 'update']);
        Route::get('/shipping-methods', [OrderController::class, 'shippingMethods']);
        Route::post('/shipping', [ShipmentController::class, 'createShipment']);
        Route::get('/shipments', [ShipmentController::class, 'getShipments']);
        Route::get('/earnings', [SellerEarningController::class, 'index']);
        Route::get('/earnings/summary', [SellerEarningController::class, 'summary']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/support', [SupportController::class, 'store']);
        Route::get('/dashboard', [SellerDashboardController::class, 'index']);
    });

    Route::get('/profile-show', [SellerController::class, 'profileshow']);
    Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);


    Route::prefix('v1')->group(function () {
        Route::apiResource('products', ProductController::class);
    });

});
