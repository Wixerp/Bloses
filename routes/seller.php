<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Auth\Seller\AuthController;
use App\Http\Controllers\Api\Seller\{SellerController, SellerRegistrationController};
use App\Http\Controllers\Api\Master\MasterTypeController;
use App\Http\Controllers\{SellerCountryController, SellerProvinceController, SellercityController};
use App\Http\Controllers\API\Product\ProductController;


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
        Route::get('/master-types/{key}', [MasterTypeController::class, 'getkeBymaster']);
        Route::get('/brands', [ProductController::class, 'getBrands']);
        Route::get('/get-questions', [ProductController::class, 'getQuestion']);
        Route::post('/questions/{questionId}/answer', [ProductController::class, 'questionOfanswer']);
        Route::get('/get-reviews', [ProductController::class, 'getReviews']);
        Route::post('/reviews/{reviewid}/answer', [ProductController::class, 'reviewsOfanswer']);
    });

    Route::get('/profile-show', [SellerController::class, 'profileshow']);
    Route::get('/profile-show-detail', [SellerController::class, 'profileshowdetail']);


    Route::prefix('v1')->group(function () {
        Route::apiResource('products', ProductController::class);
    });

});
