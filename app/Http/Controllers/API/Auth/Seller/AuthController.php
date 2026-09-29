<?php

namespace App\Http\Controllers\API\Auth\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\Seller\SellerRegisterRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\Auth\SellerAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected SellerAuthService $authService
    ) {
    }

    /**
     * Seller Register - React Web
     *
     * Cookie / Session Authentication
     */
    public function register(SellerRegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(
            $request->name,
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Seller registered successfully.',
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->getRoleNames()->first(),
            ]
        );
    }

    /**
     * Seller Register - Mobile
     *
     * Sanctum Personal Access Token
     */
    public function mobileRegister(SellerRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->mobileRegister(
            $request->name,
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Seller mobile registration successful.',
            [
                'token' => $result['token'],
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'phone' => $result['user']->phone,
                    'role' => $result['user']->getRoleNames()->first(),
                ],
            ]
        );
    }

    /**
     * Seller Login - React Web
     *
     * Cookie / Session Authentication
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWeb(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Seller login successful.',
            new AuthResource($result['user'])
        );
    }

    /**
     * Seller Logout - React Web
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logoutWeb();

        return ApiResponse::success(
            'Seller logout successful.'
        );
    }

    /**
     * Current Seller - React Web
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Seller profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

    /**
     * Seller Login - Mobile
     *
     * Sanctum Personal Access Token
     */
    public function mobileLogin(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Seller mobile login successful.',
            [
                'token' => $result['token'],
                'user' => new AuthResource($result['user']),
            ]
        );
    }

    /**
     * Seller Logout - Mobile
     */
    public function mobileLogout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user()
        );

        return ApiResponse::success(
            'Seller mobile logout successful.'
        );
    }

    /**
     * Current Seller - Mobile
     */
    public function mobileMe(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Seller mobile profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

}
