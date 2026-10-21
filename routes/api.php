<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\CloudinaryController;
use App\Http\Controllers\Api\Auth\{SocialAuthController, AuthController, PasswordResetController};
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Admin\{AdminDashboardController, ProductsController, ReviewController, OrderController, AdminEarningController,
AdminPayoutController,
AdminTransactionController, AdminCommissionController, AdminRefundController, AdminCodSettlementController,
AdminPaymentSettingsController, AdminOrderController, AdminSellerDocumentController, AdminReturnController,
AdminProductApprovalController, AdminAttributeController, AdminInventoryController, AdminSellerController,
SellerPerformanceController, StoreVerificationController, SellerSuspensionController, AdminShipmentController,
ShippingMethodController, ShippingZoneController, FailedDeliveryController, AdminShippingRateController,
OrderReportController, SellerReportController, ProductInventoryReportController, CommissionPayoutReportController,
CustomerReportController, ShippingConfigurationController, AdminProductQuestionController, AdminNotificationController,
LocationController, TaxSettingController, EmailSmsSettingController, SalesReportController, AuditLogController,
ShipmentTrackingController, RolePermissionController};
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

        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

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

        Route::get('/returns', [AdminReturnController::class, 'index']);
        Route::get('/returns/{returnId}', [AdminReturnController::class, 'show'])->whereNumber('returnId');
        Route::patch('/returns/{returnId}/status', [AdminReturnController::class, 'updateStatus'])->whereNumber('returnId');
        Route::patch('/orders/{orderId}/cancel', [AdminReturnController::class, 'cancelOrder'])->whereNumber('orderId');

        Route::get('/product-approvals', [AdminProductApprovalController::class, 'index']);
        Route::get('/product-approvals/{id}', [AdminProductApprovalController::class, 'show']);
        Route::patch('/product-approvals/{id}/approve', [AdminProductApprovalController::class, 'approve']);
        Route::patch('/product-approvals/{id}/reject', [AdminProductApprovalController::class, 'reject']);

        Route::get('/attributes', [AdminAttributeController::class, 'index']);
        Route::post('/attributes', [AdminAttributeController::class, 'store']);
        Route::get('/attributes/variants', [AdminAttributeController::class, 'variants']);
        Route::get('/attributes/variants/{variantId}', [AdminAttributeController::class, 'variantAttributes',]);
        Route::get('/attributes/{id}', [AdminAttributeController::class, 'show']);
        Route::put('/attributes/{id}', [AdminAttributeController::class, 'update']);
        Route::delete('/attributes/{id}', [AdminAttributeController::class, 'destroy']);
        Route::post('/attributes/{id}/values', [AdminAttributeController::class, 'storeValue',]);
        Route::put('/attributes/{id}/values/{valueId}', [AdminAttributeController::class, 'updateValue',]);
        Route::delete('/attributes/{id}/values/{valueId}', [AdminAttributeController::class, 'destroyValue',]);
        Route::put('/attributes/variants/{variantId}/attributes', [AdminAttributeController::class, 'syncVariantAttributes']);

        Route::get('/inventory', [AdminInventoryController::class, 'index',]);
        Route::get('/inventory/summary', [AdminInventoryController::class, 'summary',]);
        Route::put('/inventory/variants/{variantId}/stock', [AdminInventoryController::class, 'adjustStock',]);
        Route::get('/inventory/variants/{variantId}/history', [AdminInventoryController::class, 'history',]);

        Route::get('/sellers', [AdminSellerController::class, 'index']);
        Route::get('/sellers/{sellerId}', [AdminSellerController::class, 'show']);

        Route::get('/store-verifications', [StoreVerificationController::class, 'index']);
        Route::get('/store-verifications/{storeId}', [StoreVerificationController::class, 'show']);
        Route::patch('/store-verifications/{storeId}', [StoreVerificationController::class, 'update']);

        Route::get('/seller-performance', [SellerPerformanceController::class, 'index']);
        Route::get('/seller-performance/{sellerId}', [SellerPerformanceController::class, 'show']);

        Route::get('/seller-suspensions', [SellerSuspensionController::class, 'index']);
        Route::get('/seller-suspensions/{sellerId}', [SellerSuspensionController::class, 'show']);
        Route::patch('/seller-suspensions/{sellerId}/suspend', [SellerSuspensionController::class, 'suspend']);
        Route::patch('/seller-suspensions/{sellerId}/reactivate', [SellerSuspensionController::class, 'reactivate']);

        Route::get('/shipments', [AdminShipmentController::class, 'index',]);
        Route::get('/shipment-tracking', [ShipmentTrackingController::class, 'track']);

        Route::get('/shipping-rates', [AdminShippingRateController::class, 'index']);

        Route::get('/shipping-methods', [ShippingMethodController::class, 'index']);
        Route::post('/shipping-methods', [ShippingMethodController::class, 'store']);
        Route::get('/shipping-methods/{id}', [ShippingMethodController::class, 'show'])->whereNumber('id');
        Route::put('/shipping-methods/{id}', [ShippingMethodController::class, 'update'])->whereNumber('id');
        Route::patch('/shipping-methods/{id}/status', [ShippingMethodController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/shipping-methods/{id}', [ShippingMethodController::class, 'destroy'])->whereNumber('id');

        Route::get('/shipping-zones', [ShippingZoneController::class, 'index']);
        Route::post('/shipping-zones', [ShippingZoneController::class, 'store']);
        Route::get('/shipping-zones/{id}', [ShippingZoneController::class, 'show'])->whereNumber('id');
        Route::put('/shipping-zones/{id}', [ShippingZoneController::class, 'update'])->whereNumber('id');
        Route::patch('/shipping-zones/{id}/status', [ShippingZoneController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/shipping-zones/{id}', [ShippingZoneController::class, 'destroy'])->whereNumber('id');

        Route::get('/failed-deliveries', [FailedDeliveryController::class, 'index']);
        Route::get('/failed-deliveries/{id}', [FailedDeliveryController::class, 'show']);
        Route::patch('/failed-deliveries/{id}/status', [FailedDeliveryController::class, 'updateStatus']);

        Route::get('/buyer-seller-questions/', [AdminProductQuestionController::class, 'index']);
        Route::get('/buyer-seller-questions/{id}', [AdminProductQuestionController::class, 'show'])->whereNumber('id');
        Route::patch('/buyer-seller-questions/{id}/approval', [AdminProductQuestionController::class, 'updateApproval'])->whereNumber('id');

        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/notifications/{id}', [AdminNotificationController::class, 'show'])->whereNumber('id');
        Route::post('/notifications', [AdminNotificationController::class, 'store']);
        Route::delete('/notifications/{id}', [AdminNotificationController::class, 'destroy'])->whereNumber('id');
        Route::prefix('announcements')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'announcements']);
            Route::post('/', [AdminNotificationController::class, 'storeAnnouncement']);
        });

        Route::get('/sales-reports', [SalesReportController::class, 'index']);
        Route::get('/order-reports', [OrderReportController::class, 'index']);
        Route::get('/seller-reports', [SellerReportController::class, 'index']);
        Route::get('/product-inventory-reports', [ProductInventoryReportController::class, 'index']);
        Route::get('/commission-payout-reports', [CommissionPayoutReportController::class, 'index']);
        Route::get('/customer-reports', [CustomerReportController::class, 'index']);

        Route::get('/shipping-configuration', [ShippingConfigurationController::class, 'index']);
        Route::get('/shipping-methods', [ShippingConfigurationController::class, 'methods']);
        Route::post('/shipping-methods', [ShippingConfigurationController::class, 'storeMethod']);
        Route::put('/shipping-methods/{id}', [ShippingConfigurationController::class, 'updateMethod']);
        Route::delete('/shipping-methods/{id}', [ShippingConfigurationController::class, 'destroyMethod']);
        Route::get('/shipping-zones', [ShippingConfigurationController::class, 'zones']);
        Route::post('/shipping-zones', [ShippingConfigurationController::class, 'storeZone']);
        Route::put('/shipping-zones/{id}', [ShippingConfigurationController::class, 'updateZone']);
        Route::delete('/shipping-zones/{id}', [ShippingConfigurationController::class, 'destroyZone']);
        Route::get('/shipping-rates', [ShippingConfigurationController::class, 'rates']);
        Route::post('/shipping-rates', [ShippingConfigurationController::class, 'storeRate']);
        Route::put('/shipping-rates/{id}', [ShippingConfigurationController::class, 'updateRate']);
        Route::delete('/shipping-rates/{id}', [ShippingConfigurationController::class, 'destroyRate']);
        
        foreach (['countries', 'states', 'cities'] as $resource) {
            Route::get("/{$resource}", [LocationController::class, 'index'])->defaults('resource', $resource);
            Route::post("/{$resource}", [LocationController::class, 'store'])->defaults('resource', $resource);
            Route::get("/{$resource}/{id}", [LocationController::class, 'show'])->defaults('resource', $resource);
            Route::put("/{$resource}/{id}", [LocationController::class, 'update'])->defaults('resource', $resource);
            Route::patch("/{$resource}/{id}", [LocationController::class, 'update'])->defaults('resource', $resource);
            Route::delete("/{$resource}/{id}", [LocationController::class, 'destroy'])->defaults('resource', $resource);
        }

        Route::get('/tax-settings', [TaxSettingController::class, 'index']);
        Route::post('/tax-settings', [TaxSettingController::class, 'store']);
        Route::get('/tax-settings/{id}', [TaxSettingController::class, 'show']);
        Route::put('/tax-settings/{id}', [TaxSettingController::class, 'update']);
        Route::patch('/tax-settings/{id}', [TaxSettingController::class, 'update']);
        Route::delete('/tax-settings/{id}', [TaxSettingController::class, 'destroy']);
        
        Route::get('/email-sms-settings', [EmailSmsSettingController::class, 'index']);
        Route::put('/email-sms-settings', [EmailSmsSettingController::class, 'update']);
        Route::post('/email-sms-settings/test-email', [EmailSmsSettingController::class, 'testEmail']);
        Route::post('/email-sms-settings/test-sms', [EmailSmsSettingController::class, 'testSms']);

        Route::get('/audit-logs', [AuditLogController::class, 'index',]);
        Route::get('/audit-logs/{id}', [AuditLogController::class, 'show',])->whereNumber('id');
        
        // Roles
        Route::get('/roles', [RolePermissionController::class, 'indexRoles']);
        Route::post('/roles', [RolePermissionController::class, 'storeRole']);
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole']);
        Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole']);

        // Permissions
        Route::get('/permissions', [RolePermissionController::class, 'indexPermissions']);
        Route::post('/permissions', [RolePermissionController::class, 'storePermission']);
        Route::put('/permissions/{permission}', [RolePermissionController::class, 'updatePermission']);
        Route::delete('/permissions/{permission}', [RolePermissionController::class, 'destroyPermission']);

        // Assign permissions to a role
        Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'syncRolePermissions']);

        // Assign roles or direct permissions to a user
        Route::put('/users/{user}/roles', [RolePermissionController::class, 'assignUserRoles']);
        Route::put('/users/{user}/permissions', [RolePermissionController::class, 'syncUserPermissions']);

        // Inspect effective access
        Route::get('/users/{user}/access', [RolePermissionController::class, 'userAccess']);
    });

});

Route::get('/categories', [CategoriesController::class, 'getCategories']);
Route::get('/master-types/{key}', [MasterTypeController::class, 'getByKey']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);


Route::get('/test', function () {
    return 'api run';
});
