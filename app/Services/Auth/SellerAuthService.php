<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\{Auth, Hash, DB, Mail, Cache};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use App\Jobs\SendSellerRegistrationOtp;



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

    // public function sendRegistrationOtp(string $name, string $email, string $password): array
    // {
    //     $email = strtolower(trim($email));

    //     if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
    //         throw ValidationException::withMessages([
    //             'email' => ['This email is already registered.'],
    //         ]);
    //     }

    //     $cacheKey    = 'seller_register_otp:' . $email;
    //     $throttleKey = 'seller_register_throttle:' . $email;

    //     if (!Cache::add($throttleKey, true, now()->addMinute())) {
    //         throw ValidationException::withMessages([
    //             'email' => ['Please wait one minute before requesting another OTP.'],
    //         ]);
    //     }

    //     $otp = (string) random_int(100000, 999999);

    //     Cache::put($cacheKey, [
    //         'name'          => $name,
    //         'email'         => $email,
    //         'password_hash' => Hash::make($password),
    //         'otp_hash'      => Hash::make($otp),
    //     ], now()->addMinutes(5));

    //     Cache::put($cacheKey . ':attempts', 0, now()->addMinutes(5));

    //     try {
    //         Mail::raw(
    //             "Your Blose seller registration OTP is: {$otp}\n\n"
    //             . "This OTP expires in 5 minutes. Do not share it with anyone.",
    //             function ($message) use ($email) {
    //                 $message->to($email)->subject('Blose Seller Registration OTP');
    //             }
    //         );
    //     } catch (\Throwable $e) {
    //         Cache::forget($cacheKey);
    //         Cache::forget($cacheKey . ':attempts');
    //         Cache::forget($throttleKey);
    //         report($e);

    //         throw ValidationException::withMessages([
    //             'email' => ['Unable to send OTP email. Please try again later.'],
    //         ]);
    //     }

    //     return [
    //         'email'      => $email,
    //         'expires_in' => 300,
    //         'message'    => 'OTP sent successfully. Please verify your email.',
    //     ];
    // }
    // public function sendRegistrationOtp(string $name, string $email, string $password): array
    // {
    //     $email = strtolower(trim($email));

    //     if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
    //         throw ValidationException::withMessages([
    //             'email' => ['This email is already registered.'],
    //         ]);
    //     }

    //     $cacheKey    = 'seller_register_otp:' . $email;
    //     $throttleKey = 'seller_register_throttle:' . $email;

    //     if (!Cache::add($throttleKey, true, now()->addMinute())) {
    //         throw ValidationException::withMessages([
    //             'email' => ['Please wait one minute before requesting another OTP.'],
    //         ]);
    //     }

    //     $otp = (string) random_int(100000, 999999);

    //     // Pehle cache mein data daal do
    //     Cache::put($cacheKey, [
    //         'name'          => $name,
    //         'email'         => $email,
    //         'password_hash' => Hash::make($password),
    //         'otp_hash'      => Hash::make($otp),
    //     ], now()->addMinutes(5));

    //     Cache::put($cacheKey . ':attempts', 0, now()->addMinutes(5));

    //     // Email background mein bhejo (response ke baad)
    //     dispatch(function () use ($email, $otp, $cacheKey, $throttleKey) {
    //         try {
    //             Mail::raw(
    //                 "Your Blose seller registration OTP is: {$otp}\n\n"
    //                 . "This OTP expires in 5 minutes. Do not share it with anyone.",
    //                 function ($message) use ($email) {
    //                     $message->to($email)->subject('Blose Seller Registration OTP');
    //                 }
    //             );
    //         } catch (\Throwable $e) {
    //             // Email fail ho gayi to cache clear kar do
    //             Cache::forget($cacheKey);
    //             Cache::forget($cacheKey . ':attempts');
    //             Cache::forget($throttleKey);

    //             report($e);
    //         }
    //     })->afterResponse();

    //     // Turant response return
    //     return [
    //         'email'      => $email,
    //         'expires_in' => 300,
    //         'message'    => 'OTP sent successfully. Please verify your email.',
    //     ];
    // }
    public function sendRegistrationOtp(string $name, string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $cacheKey    = 'seller_register_otp:' . $email;
        $throttleKey = 'seller_register_throttle:' . $email;

        if (!Cache::add($throttleKey, true, now()->addMinute())) {
            throw ValidationException::withMessages([
                'email' => ['Please wait one minute before requesting another OTP.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        // Pehle cache mein data daal do
        Cache::put($cacheKey, [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => Hash::make($password),
            'otp_hash'      => Hash::make($otp),
        ], now()->addMinutes(5));

        Cache::put($cacheKey . ':attempts', 0, now()->addMinutes(5));

        // Job dispatch karo (background mein email jayegi)
        SendSellerRegistrationOtp::dispatch($email, $otp, $cacheKey, $throttleKey);

        // Turant response return
        return [
            'email'      => $email,
            'expires_in' => 300,
            'message'    => 'OTP sent successfully. Please verify your email.',
        ];
    }

    public function verifyRegistrationOtp(string $email, string $otp): User
    {
        $email = strtolower(trim($email));

        $cacheKey   = 'seller_register_otp:' . $email;
        $attemptKey = $cacheKey . ':attempts';

        $registration = Cache::get($cacheKey);

        if (!$registration) {
            throw ValidationException::withMessages([
                'otp' => ['OTP expired or not requested. Please request a new OTP.'],
            ]);
        }

        $attempts = (int) Cache::get($attemptKey, 0);

        if ($attempts >= 5) {
            Cache::forget($cacheKey);
            Cache::forget($attemptKey);

            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new OTP.'],
            ]);
        }

        if (!is_string($otp) || !Hash::check($otp, $registration['otp_hash'])) {
            $attempts++;
            Cache::put($attemptKey, $attempts, now()->addMinutes(5));

            if ($attempts >= 5) {
                Cache::forget($cacheKey);
                Cache::forget($attemptKey);
            }

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP. Attempts remaining: ' . max(0, 5 - $attempts)],
            ]);
        }

        // OTP sahi hai → abhi DB mein user create karo
        $user = DB::transaction(function () use ($registration, $email) {
            $exists = User::whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered.'],
                ]);
            }

            $user = User::create([
                'name'      => $registration['name'],
                'email'     => $registration['email'],
                'password'  => $registration['password_hash'], // already hashed
                'is_active' => 1,
            ]);

            $user->assignRole('seller');

            return $user->fresh();
        });

        // Cache clear
        Cache::forget($cacheKey);
        Cache::forget($attemptKey);

        return $user;
    }

    public function resendRegistrationOtp(string $email): array
    {
        $email = strtolower(trim($email));

        $cacheKey    = 'seller_register_otp:' . $email;
        $throttleKey = 'seller_register_throttle:' . $email;
        $attemptKey  = $cacheKey . ':attempts';

        $registration = Cache::get($cacheKey);

        if (!$registration) {
            throw ValidationException::withMessages([
                'email' => ['No pending registration found. Please register again.'],
            ]);
        }

        // Throttle: 1 minute wait
        if (!Cache::add($throttleKey, true, now()->addMinute())) {
            throw ValidationException::withMessages([
                'email' => ['Please wait one minute before requesting another OTP.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        // Purana data rakh ke sirf naya OTP update karo
        Cache::put($cacheKey, [
            'name'          => $registration['name'],
            'email'         => $registration['email'],
            'password_hash' => $registration['password_hash'],
            'otp_hash'      => Hash::make($otp),
        ], now()->addMinutes(5));

        // Attempts reset
        Cache::put($attemptKey, 0, now()->addMinutes(5));

        // Background mein email bhejo
        SendSellerRegistrationOtp::dispatch($email, $otp, $cacheKey, $throttleKey);

        return [
            'email'      => $email,
            'expires_in' => 300,
            'message'    => 'OTP resent successfully. Please check your email.',
        ];
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
