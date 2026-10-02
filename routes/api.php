<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\Auth\{SocialAuthController, AuthController};
use App\Http\Controllers\API\CloudinaryController;
use App\Http\Controllers\API\Product\ProductController;
use App\Http\Controllers\API\Admin\ProductsController;
use App\Http\Controllers\API\MasterType\MasterTypeController;
use App\Http\Controllers\Api\Seller\SellerController;
use App\Http\Controllers\API\Categories\CategoriesController;


Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

Route::post('/cloudinary/upload', [CloudinaryController::class, 'upload']);

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);

    // Protected Admin Routes
    Route::middleware(['auth:sanctum', 'role:admin',])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'adminLogout']);
        Route::post('/seller-approval', [SellerController::class, 'sellerprofileapproval']);

        Route::post('/categories', [CategoriesController::class, 'postCategories']);
        Route::get('/categories/{id}', [CategoriesController::class, 'getCategory']);
        Route::put('/categories/{id}', [CategoriesController::class, 'updateCategories']);
        Route::delete('/categories/{id}', [CategoriesController::class, 'deleteCategories']);

        Route::get('/products/sellers', [ ProductsController::class, 'sellers']);
        Route::get('/products/sellers/{sellerId}/stores', [ ProductsController::class, 'sellerStores']);
        Route::get('/products/stores/{storeId}/products', [ ProductsController::class, 'storeProducts']);
        Route::get('/products', [ ProductsController::class, 'allProducts']);

    });

});

Route::get('/categories', [CategoriesController::class, 'getCategories']);

Route::prefix('seller')->group(function () {
    //category

});

Route::get('/master-types/{key}', [MasterTypeController::class, 'getByKey']);

/*
|--------------------------------------------------------------------------
| Test composer dump-autoload
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return 'api run';
});
