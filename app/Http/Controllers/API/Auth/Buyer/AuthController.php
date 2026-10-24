<?php

namespace App\Http\Controllers\Api\Auth\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\Buyer\BuyerRegisterRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\Auth\BuyerAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected BuyerAuthService $authService
    ) {
    }

    // public function register(BuyerRegisterRequest $request): JsonResponse
    // {
    //     $user = $this->authService->register(
    //         $request->name,
    //         $request->email,
    //         $request->password
    //     );

    //     return ApiResponse::success(
    //         'Buyer registered successfully.',
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

    public function mobileRegister(BuyerRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->mobileRegister(
            $request->name,
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Buyer mobile registration successful.',
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

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWeb(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Buyer login successful.',
            new AuthResource($result['user'])
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logoutWeb();

        return ApiResponse::success(
            'Buyer logout successful.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Buyer profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

    public function mobileLogin(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        return ApiResponse::success(
            'Buyer mobile login successful.',
            [
                'token' => $result['token'],
                'user' => new AuthResource($result['user']),
            ]
        );
    }

    public function mobileLogout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user()
        );

        return ApiResponse::success(
            'Buyer mobile logout successful.'
        );
    }

    public function mobileMe(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Buyer mobile profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

    public function profile(): JsonResponse
    {
        $profile = $this->authService->getProfile();

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => $profile,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required','email','max:255','unique:users,email,' . auth()->id(),],
            'phone' => ['nullable', 'string', 'max:50'],
            'birthday' => ['nullable', 'string', 'max:100'],
            'gender_id' => ['nullable', 'integer'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120',],
        ]);

        $profile = $this->authService->updateProfile(
            $data,
            $request->file('avatar')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $profile,
        ]);
    }
}
