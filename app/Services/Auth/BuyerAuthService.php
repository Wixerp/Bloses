<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\{Auth, Hash, DB};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class BuyerAuthService
{
    public function register(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => 1,
        ]);

        $user->assignRole('buyer');

        return $user->fresh();
    }

    protected function authenticate(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.'
                ],
            ]);
        }

        if (!$user->hasAnyRole(['seller', 'buyer'])) {
            throw ValidationException::withMessages([
                'email' => [
                    'You are not authorized to use the mobile application.'
                ],
            ]);
        }

        return $user;
    }

    public function mobileRegister(string $name, string $email, string $password): array {
        $user = $this->register($name, $email, $password);

        $token = $user->createToken('buyer-mobile-token')->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
        ];
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
        $token = $user->createToken('buyer-mobile-token')->plainTextToken;

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

        $user = Auth::user();
        if ((int) $user->is_block === 1) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [
                    'Your account has been blocked. Please contact support.'
                ],
            ]);
        }
        if (!$user->hasRole('buyer')) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['You are not authorized to access the buyer portal.'],
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

    public function getProfile()
    {
        if (!auth()->check()) {
            abort(401, 'Please login to view your profile.');
        }

        $userId = auth()->id();
        return DB::table('users')
            ->leftJoin('master_types as gender','gender.id','=','users.gender_id')
            ->where('users.id', $userId)
            ->select(['users.id','users.name','users.email','users.phone',
                'users.birthday','users.gender_id','gender.description as gender',
                DB::raw("CASE WHEN users.buyerimg IS NOT NULL
                        THEN CONCAT('" . config('app.url') . "/storage/', users.buyerimg) ELSE NULL
                    END AS buyerimg
                "),
            ])->first();
    }

    public function updateProfile(array $data, $avatar = null)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to update your profile.');
        }

        $userId = auth()->id();

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'birthday' => $data['birthday'] ?? null,
            'gender_id' => $data['gender_id'] ?? null,
            'updated_at' => now(),
        ];

        // Buyer avatar
        if ($avatar) {
            $path = $avatar->store('avatars', 'public');

            $updateData['buyerimg'] = $path;
        }

        DB::table('users')
            ->where('id', $userId)
            ->update($updateData);

        return $this->getProfile();
    }

}
