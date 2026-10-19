<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\Auth\AdminAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, DB};
use App\Models\User;

class AuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $adminAuthService
    ) {
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
            'role' => ['required', 'in:buyer,seller,admin'],
        ]);

        $user = $this->adminAuthService->register(
            $data['name'],
            $data['email'],
            $data['password'],
            $data['role']
        );

        return ApiResponse::success(
            'User registration successful.',
            new AuthResource($user)
        );
    }

    public function index()
    {
        $users = $this->adminAuthService->getAllUsers();

        return ApiResponse::success(
            'Users retrieved successfully.',
            AuthResource::collection($users)
        );
    }

    public function show(int $id)
    {
        $user = $this->adminAuthService->getUser($id);

        return ApiResponse::success(
            'User retrieved successfully.',
            new AuthResource($user)
        );
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'role' => [
                'required', Rule::in(['buyer', 'seller', 'admin'])
            ],
            'password' => [
                'nullable', 'string', 'min:8', 'confirmed'
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = $this->adminAuthService->updateUser($id, $data);

        return ApiResponse::success(
            'User updated successfully.',
            new AuthResource($user)
        );
    }

    public function destroy(Request $request, int $id)
    {
        if ((int) $request->user()->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $this->adminAuthService->deleteUser($id);

        return ApiResponse::success(
            'User deleted successfully.',
            null
        );
    }

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

    public function adminLogout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(
            'Admin logout successful.'
        );
    }

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

    public function mobileMe(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Profile fetched successfully.',
            new AuthResource($request->user())
        );
    }

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
