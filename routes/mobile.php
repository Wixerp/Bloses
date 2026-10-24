<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Auth\Seller\AuthController as SellerAuthController;
use App\Http\Controllers\Api\Auth\Buyer\AuthController as BuyerAuthController;
use App\Http\Controllers\Api\Master\MasterTypeController as MasterTypeController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController, SellerEarningController, SellerDashboardController,
SellerPaymentSettingsController, SellerCodSettlementController, SellerTransactionController, SellerCommissionController,
SellerRefundController, StoreManagementController, BusinessVerificationController, SellerReportController,
SellerMarketingController};
use App\Http\Controllers\SocialIntegrationController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Categories\CategoriesController;
use App\Http\Controllers\Api\Buyer\{WishlistController, AddressController};
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Shipping\ShipmentController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Support\{SupportController, BuyerSellerChatController};
use App\Http\Controllers\Api\Seller\SellerPayoutController;
use App\Http\Controllers\Api\Suppliers\SuppliersController;



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

            Route::post('/suppliers', [SuppliersController::class, 'store']);
            Route::get('/suppliers/{id}', [SuppliersController::class, 'show']);
            Route::put('/suppliers/{id}', [SuppliersController::class, 'update']);
            Route::patch('/suppliers/{id}', [SuppliersController::class, 'update']);
            Route::delete('/suppliers/{id}', [SuppliersController::class, 'destroy']);

            Route::get('/payouts/balance', [SellerPayoutController::class, 'balance']);
            Route::get('/payouts', [SellerPayoutController::class, 'index']);
            Route::post('/payouts', [SellerPayoutController::class, 'store']);

            Route::get('/payment-settings', [SellerPaymentSettingsController::class, 'index']);
            Route::get('/payment-settings/methods', [SellerPaymentSettingsController::class, 'methods']);

            Route::get('/cod-settlements/summary', [SellerCodSettlementController::class, 'summary']);
            Route::get('/cod-settlements', [SellerCodSettlementController::class, 'index']);
            Route::get('/cod-settlements/{id}', [SellerCodSettlementController::class, 'show']);

            Route::get('/transactions/summary', [SellerTransactionController::class, 'summary']);
            Route::get('/transactions', [SellerTransactionController::class, 'index']);

            Route::get('/refunds/summary', [SellerRefundController::class, 'summary']);
            Route::get('/refunds', [SellerRefundController::class, 'index']);
            Route::get('/refunds/{id}', [SellerRefundController::class, 'show']);

            Route::get('/dashboard', [SellerDashboardController::class, 'index']);
            Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);

            Route::get('/store', [StoreManagementController::class, 'show']);
            Route::put('/store', [StoreManagementController::class, 'update']);

            Route::get('/verification', [BusinessVerificationController::class, 'show']);
            Route::put('/verification/business', [BusinessVerificationController::class, 'updateBusiness']);
            Route::get('/verification/addresses', [BusinessVerificationController::class, 'addresses']);
            Route::post('/verification/addresses', [BusinessVerificationController::class, 'saveAddress']);
            Route::put('/verification/addresses/{id}', [BusinessVerificationController::class, 'updateAddress']);
            Route::get('/verification/bank-accounts', [BusinessVerificationController::class, 'bankAccounts']);
            Route::post('/verification/bank-accounts', [BusinessVerificationController::class, 'saveBankAccount']);
            Route::post('/verification/documents', [BusinessVerificationController::class, 'uploadDocument']);
            Route::delete('/verification/documents/{id}', [BusinessVerificationController::class, 'deleteDocument']);

            Route::prefix('reports')->group(function () {
                Route::get('/summary', [SellerReportController::class, 'summary']);
                Route::get('/sales-by-date', [SellerReportController::class, 'salesByDate']);
                Route::get('/orders', [SellerReportController::class, 'orders']);
                Route::get('/best-selling-products', [SellerReportController::class, 'bestSellingProducts']);
                Route::get('/inventory', [SellerReportController::class, 'inventory']);
                Route::get('/order-status-summary', [SellerReportController::class, 'orderStatusSummary']);
            });

            Route::get('/marketing/products', [SellerMarketingController::class, 'products']);
            Route::patch('/marketing/products/{productId}/promotion', [SellerMarketingController::class, 'updatePromotion'])->whereNumber('productId');
            Route::get('/marketing/coupon-usage', [SellerMarketingController::class, 'couponUsageReport']);

            Route::get('/connect/{provider}', [SocialIntegrationController::class, 'start',])->where('provider', 'meta|tiktok');
            Route::get('/accounts', [SocialIntegrationController::class, 'accounts',]);
            Route::delete('/accounts/{id}', [SocialIntegrationController::class, 'disconnect',])->whereNumber('id');
            Route::get('/posts', [SocialIntegrationController::class, 'posts',]);
            Route::post('/accounts/{id}/sync', [SocialIntegrationController::class,  'syncPosts',])->whereNumber('id');
            Route::patch('/posts/{postId}/product', [SocialIntegrationController::class, 'mapProduct',])->whereNumber('postId');
            Route::get('/sync-history', [SocialIntegrationController::class, 'syncHistory',]);

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
            Route::get('/orders/{orderId}/chat', [BuyerSellerChatController::class, 'openBuyerChat']);
            Route::post('/orders/{orderId}/chat/messages', [BuyerSellerChatController::class, 'buyerSendMessage']);
            Route::get('/wishlist', [WishlistController::class, 'index']);
            Route::post('/wishlist', [WishlistController::class, 'wishlist']);
            Route::post('/review-like', [ProductController::class, 'reviewLike']);
            Route::get('/suppliers', [SuppliersController::class, 'index']);


            Route::post('/logout', [BuyerAuthController::class,'mobileLogout']);
        });

    });

});
