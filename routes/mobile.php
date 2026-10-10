<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Auth\Seller\AuthController as SellerAuthController;
use App\Http\Controllers\Api\Auth\Buyer\AuthController as BuyerAuthController;
use App\Http\Controllers\Api\Master\MasterTypeController as MasterTypeController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController, SellerEarningController, SellerDashboardController};
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Categories\CategoriesController;
use App\Http\Controllers\Api\Buyer\{WishlistController, AddressController};
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Support\SupportController;


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
            Route::get('/orders/returns', [OrderController::class, 'sellerReturnOrders']);
            Route::get('/orders/{orderId}', [OrderController::class, 'sellerOrderDetail']);
            Route::put('/order-status-update', [OrderController::class, 'updateOrderStatus']);
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::put('/inventory/{productId}', [InventoryController::class, 'update']);

            Route::get('/profile-show', [SellerController::class, 'profileshow']);
            Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);

            Route::get('/get-reviews', [ProductController::class, 'getReviews']);
            Route::post('/reviews/{reviewid}/answer', [ProductController::class, 'reviewsOfanswer']);
            Route::get('/shipping-methods', [OrderController::class, 'shippingMethods']);
            Route::post('/shipping', [ShipmentController::class, 'createShipment']);
            Route::get('/shipments', [ShipmentController::class, 'getShipments']);
            Route::get('/earnings', [SellerEarningController::class, 'index']);
            Route::get('/earnings/summary', [SellerEarningController::class, 'summary']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('/support', [SupportController::class, 'myTickets']);
            Route::post('/support', [SupportController::class, 'store']);
            Route::get('/support/{id}', [SupportController::class, 'myTicketDetail']);
            Route::get('/dashboard', [SellerDashboardController::class, 'index']);
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
