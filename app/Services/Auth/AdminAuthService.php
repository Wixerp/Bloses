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

    public function getUser(int $id): User
    {
        return User::with('roles')->findOrFail($id);
    }

    public function getAllUsers()
    {
        return User::with('roles')
            ->orderByDesc('id')
            ->get();
    }

    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user = User::findOrFail($id);

            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (array_key_exists('is_active', $data)) {
                $userData['is_active'] = $data['is_active'];
            }

            if (!empty($data['password'])) {
                $userData['password'] = Hash::make(
                    $data['password']
                );
            }

            $user->update($userData);

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            return $user->fresh()->load('roles');
        });
    }

    // Delete user
    public function deleteUser(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $user = User::findOrFail($id);

            return (bool) $user->delete();
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
