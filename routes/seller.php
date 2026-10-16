<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Auth\Seller\AuthController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController, SellerEarningController,
SellerPayoutController, SellerBankAccountController, SellerDashboardController, SellerPaymentSettingsController,
SellerCodSettlementController, SellerTransactionController, SellerCommissionController, SellerRefundController};
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Master\MasterTypeController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Suppliers\SuppliersController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Support\SupportController;

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
        Route::get('/support', [SupportController::class, 'myTickets']);
        Route::post('/support/{id?}', [SupportController::class, 'store']);
        Route::get('/support/{id}', [SupportController::class, 'myTicketDetail']);

        Route::get('/suppliers', [SuppliersController::class, 'index']);
        Route::post('/suppliers', [SuppliersController::class, 'store']);
        Route::get('/suppliers/{id}', [SuppliersController::class, 'show']);
        Route::put('/suppliers/{id}', [SuppliersController::class, 'update']);
        Route::patch('/suppliers/{id}', [SuppliersController::class, 'update']);
        Route::delete('/suppliers/{id}', [SuppliersController::class, 'destroy']);

        Route::get('/payouts/balance', [SellerPayoutController::class, 'balance']);
        Route::get('/payouts', [SellerPayoutController::class, 'index']);
        Route::post('/payouts', [SellerPayoutController::class, 'store']);

        Route::get('/bank-accounts', [SellerBankAccountController::class, 'index']);
        Route::post('/bank-accounts', [SellerBankAccountController::class, 'store']);
        Route::get('/bank-accounts/{id}', [SellerBankAccountController::class, 'show']);
        Route::put('/bank-accounts/{id}', [SellerBankAccountController::class, 'update']);
        Route::delete('/bank-accounts/{id}', [SellerBankAccountController::class, 'destroy']);
        Route::get('/bank-accounts/payout-options', [SellerBankAccountController::class, 'payoutOptions']);

        Route::get('/payment-settings', [SellerPaymentSettingsController::class, 'index']);
        Route::get('/payment-settings/methods', [SellerPaymentSettingsController::class, 'methods']);

        Route::get('/cod-settlements/summary', [SellerCodSettlementController::class, 'summary']);
        Route::get('/cod-settlements', [SellerCodSettlementController::class, 'index']);
        Route::get('/cod-settlements/{id}', [SellerCodSettlementController::class, 'show']);

        Route::get('/transactions/summary', [SellerTransactionController::class, 'summary']);
        Route::get('/transactions', [SellerTransactionController::class, 'index']);

        Route::get('/commission-details/summary', [SellerCommissionController::class, 'summary']);
        Route::get('/commission-details', [SellerCommissionController::class, 'index']);
        Route::get('/commission-details/{id}', [SellerCommissionController::class, 'show']);

        Route::get('/refunds/summary', [SellerRefundController::class, 'summary']);
        Route::get('/refunds', [SellerRefundController::class, 'index']);
        Route::get('/refunds/{id}', [SellerRefundController::class, 'show']);

        Route::get('/dashboard', [SellerDashboardController::class, 'index']);
        Route::get('/profile-status-check/{id}', [SellerController::class, 'profilestatscheck']);
    });

    Route::get('/profile-show', [SellerController::class, 'profileshow']);
    Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);


    Route::prefix('v1')->group(function () {
        Route::apiResource('products', ProductController::class);
    });

});
