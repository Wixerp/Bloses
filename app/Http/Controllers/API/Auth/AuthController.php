<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\Auth\AdminAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $adminAuthService
    ) {
    }

    /**
     * Admin Login
     *
     * React Web
     * Cookie / Session Authentication
     */
    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->adminAuthService->loginWeb(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Admin login successful.',
            new AuthResource($result['user'])
        );
    }

    /**
     * Admin Logout
     */
    public function adminLogout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(
            'Admin logout successful.'
        );
    }

    /**
     * Logged In Admin
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Admin profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

    // Mobile Login =====================================================================
    public function mobileLogin(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->adminAuthService->login(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Login successful.',
            [
                'token' => $result['token'],
                'user' => new AuthResource($result['user']),
            ]
        );
    }

    /**
     * Mobile Logged In User
     */
    public function mobileMe(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

    /**
     * Mobile Logout
     */
    public function mobileLogout(Request $request): JsonResponse
    {
        $this->adminAuthService->logout(
            $request->user()
        );

        return ApiResponse::success(
            'Logout successful.'
        );
    }
}
