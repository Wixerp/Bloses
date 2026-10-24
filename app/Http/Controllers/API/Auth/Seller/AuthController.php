<?php

namespace App\Http\Controllers\Api\Auth\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Resources\Auth\AuthResource;
use Illuminate\Http\{JsonResponse, Request};
use App\Http\Requests\Auth\Seller\SellerRegisterRequest;
use App\Services\Auth\SellerAuthService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected SellerAuthService $authService
    ) {
    }

    // public function register(SellerRegisterRequest $request): JsonResponse
    // {
        //     $user = $this->authService->register(
        //         $request->name,
        //         $request->email,
        //         $request->password
        //     );

        //     return ApiResponse::success(
        //         'Seller registered successfully.',
        //         [
        //             'id' => $user->id,
        //             'name' => $user->name,
        //             'email' => $user->email,
        //             'phone' => $user->phone,
        //             'role' => $user->getRoleNames()->first(),
        //         ]
        //     );
    // }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $result = $this->authService->sendRegistrationOtp(
            $validated['name'],
            $validated['email'],
            $validated['password']
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email. Verify it to complete registration.',
            'data' => $result,
        ], 200);
    }

    public function verifyRegistrationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->authService->verifyRegistrationOtp(
            $validated['email'],
            $validated['otp']
        );

        return response()->json([
            'success' => true,
            'message' => 'Email verified and seller registered successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'seller',
            ],
        ], 201);
    }

    public function resendRegistrationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->authService->resendRegistrationOtp($validated['email']);

        return response()->json([
            'success' => true,
            'message' => 'OTP resent successfully.',
            'data'    => $result,
        ], 200);
    }

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
