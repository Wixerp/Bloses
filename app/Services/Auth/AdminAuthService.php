<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\{Auth, Hash, DB};
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class AdminAuthService
{
    protected function authenticate(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'email' => ['You are not authorized to access the admin portal.'],
            ]);
        }

        return $user;
    }

    public function register(string $name, string $email, string $password, string $role)
    {
        return DB::transaction(function () use ($name, $email, $password, $role)
        {

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => 1,
            ]);

            $user->assignRole($role);

            return $user->fresh();
        });
    }

    public function login(string $email, string $password, string $login_type): array
    {
        $user = $this->authenticate($email, $password);
        $user->tokens()->delete();
        $token = $user->createToken('mobile-token')->plainTextToken;

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
        $user = $this->authenticate($email, $password);

        if (!Auth::attempt([
            'email' => $email,
            'password' => $password,
        ])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
        if (!$user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'email' => ['You are not authorized to access the admin portal.'],
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
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
