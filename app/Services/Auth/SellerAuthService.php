<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class SellerAuthService
{
    public function register(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => 1,
        ]);

        $user->assignRole('seller');

        return $user->fresh();
    }

    protected function authenticate(string $email, string $password): User {

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.'
                ],
            ]);
        }

        if (!$user->hasRole('seller')) {
            throw ValidationException::withMessages([
                'email' => [
                    'You are not authorized to access the seller portal.'
                ],
            ]);
        }

        return $user;
    }

    public function login(string $email, string $password): array {

        $user = $this->authenticate($email, $password);
        if ((int) $user->is_block === 1) {
            throw ValidationException::withMessages([
                'email' => [
                    'Your account has been blocked. Please contact support.'
                ],
            ]);
        }
        $user->tokens()->delete();
        $token = $user->createToken('seller-mobile-token')->plainTextToken;

        $user->update([
            'last_login_at' => now(),
        ]);

        return [
            'user' => $user->fresh(),
            'token' => $token,
        ];
    }

    public function loginWeb(string $email, string $password): array
    {
        if (!Auth::attempt([
            'email' => $email,
            'password' => $password,
        ])) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.'
                ],
            ]);
        }

        // Authenticated user
        $user = Auth::user();
        if ((int) $user->is_block === 1) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [
                    'Your account has been blocked. Please contact support.'
                ],
            ]);
        }

        if (!Auth::user()->hasRole('seller')) {
            throw ValidationException::withMessages([
                'email' => [
                    'You are not authorized to access the seller portal.'
                ],
            ]);
        }

        request()->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
        ]);

        return [
            'user' => $user->fresh(),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function logoutWeb(): void
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function mobileRegister(
        string $name,
        string $email,
        string $password
    ): array {
        $user = $this->register($name, $email, $password);

        $token = $user->createToken('seller-mobile-token')->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
        ];
    }

}
