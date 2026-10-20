<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\CloudinaryController;
use App\Http\Controllers\Api\Auth\{SocialAuthController, AuthController, PasswordResetController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Admin\{ProductsController, ReviewController, OrderController, AdminEarningController, AdminPayoutController,
AdminTransactionController, AdminCommissionController, AdminRefundController, AdminCodSettlementController,
AdminPaymentSettingsController, AdminOrderController, AdminSellerDocumentController};
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
        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::get('/users/{id}', [AuthController::class, 'show']);
        Route::put('/users/{id}', [AuthController::class, 'update']);
        Route::patch('/users/{id}', [AuthController::class, 'update']);
        Route::delete('/users/{id}', [AuthController::class, 'destroy']);

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
        
        Route::get('/orders/details', [AdminOrderController::class, 'index']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::get('/earnings', [AdminEarningController::class, 'index']);
        Route::get('/earnings/summary', [AdminEarningController::class, 'summary']);

        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::get('/payouts/summary', [AdminPayoutController::class, 'summary']);
        Route::put('/payouts/{id}/approve', [AdminPayoutController::class, 'approve']);
        Route::put('/payouts/{id}/reject', [AdminPayoutController::class, 'reject']);
        Route::put('/payouts/{id}/processing', [AdminPayoutController::class, 'processing']);
        Route::put('/payouts/{id}/paid', [AdminPayoutController::class, 'paid']);

        Route::get('/transactions', [AdminTransactionController::class, 'index',]);
        Route::get('/transactions/summary', [AdminTransactionController::class, 'summary',]);

        Route::get('/commissions', [AdminCommissionController::class, 'index',]);
        Route::post('/commissions', [AdminCommissionController::class, 'store',]);
        Route::get('/commissions/history', [AdminCommissionController::class, 'history',]);
        Route::get('/commissions/{id}', [AdminCommissionController::class, 'show',])->whereNumber('id');
        Route::put('/commissions/{id}', [AdminCommissionController::class, 'update',])->whereNumber('id');
        Route::patch('/commissions/{id}/status', [AdminCommissionController::class, 'updateStatus',])->whereNumber('id');

        Route::get('/refunds/summary', [AdminRefundController::class, 'summary',]);
        Route::get('/refunds', [AdminRefundController::class, 'index',]);
        Route::get('/refunds/{id}', [AdminRefundController::class, 'show',])->whereNumber('id');
        Route::post('/refunds/{id}/approve', [AdminRefundController::class, 'approve',])->whereNumber('id');
        Route::post('/refunds/{id}/reject', [AdminRefundController::class, 'reject',])->whereNumber('id');
        Route::post('/refunds/{id}/mark-refunded', [AdminRefundController::class, 'markRefunded',])->whereNumber('id');

        Route::get('/cod-settlements/summary',[AdminCodSettlementController::class, 'summary']);
        Route::get('/cod-settlements',[AdminCodSettlementController::class, 'index']);
        Route::get('/cod-settlements/{id}',[AdminCodSettlementController::class, 'show'])->whereNumber('id');
        Route::put('/cod-settlements/{id}/remittance',[AdminCodSettlementController::class, 'updateRemittance'])->whereNumber('id');

        Route::get('/payment-settings', [AdminPaymentSettingsController::class, 'index']);
        Route::put('/payment-settings', [AdminPaymentSettingsController::class, 'update']);
        Route::patch('/{key}', [AdminPaymentSettingsController::class, 'updateOne']);
        Route::get('/methods', [AdminPaymentSettingsController::class, 'methods']);
        Route::patch('/methods/{id}', [AdminPaymentSettingsController::class, 'updateMethod']);

        Route::get('/tickets', [AdminSupportController::class, 'index']);
        Route::get('/tickets/{id}', [AdminSupportController::class, 'show']);
        Route::post('/tickets/{id}/reply', [AdminSupportController::class, 'reply']);
        Route::patch('/tickets/{id}/status', [AdminSupportController::class, 'updateStatus']);


        Route::get('/sellers/{sellerId}/documents', [AdminSellerDocumentController::class, 'index']);
        Route::get('/seller-documents/{documentId}/view', [AdminSellerDocumentController::class, 'view']);
        Route::get('/seller-documents/{documentId}/download', [AdminSellerDocumentController::class, 'download']);
        Route::patch('/seller-documents/{documentId}/status', [AdminSellerDocumentController::class, 'updateStatus']);
    });

});

Route::get('/categories', [CategoriesController::class, 'getCategories']);
Route::get('/master-types/{key}', [MasterTypeController::class, 'getByKey']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);


Route::get('/test', function () {
    return 'api run';
});
