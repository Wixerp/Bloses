<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetOtpController extends Controller
{
    private const OTP_TTL = 300;       // 5 minutes
    private const RESET_TTL = 600;     // 10 minutes
    private const MAX_ATTEMPTS = 5;

    private function response(
        bool $success,
        string $message,
        mixed $data = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Only allow buyer, seller, or admin.
     */
    private function validRole(string $role): bool
    {
        return in_array($role, ['buyer', 'seller', 'admin'], true);
    }

    private function userForRole(string $email, string $role): ?User
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        if (!$user || !$user->hasRole($role)) {
            return null;
        }

        return $user;
    }

    private function cachePrefix(string $email, string $role): string
    {
        return 'password_reset_otp:' . hash(
            'sha256',
            strtolower(trim($email)) . '|' . $role
        );
    }

    /**
     * Step 1: Send OTP to registered email.
     * POST /api/forgot-password/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:buyer,seller,admin'],
        ]);

        $email = strtolower(trim($validated['email']));
        $role = $validated['role'];

        $prefix = $this->cachePrefix($email, $role);

        // Prevent repeated OTP requests for 60 seconds.
        $throttleKey = $prefix . ':send-throttle';

        if (!Cache::add($throttleKey, true, 60)) {
            return $this->response(
                false,
                'Please wait before requesting another OTP.',
                null,
                429
            );
        }

        $user = $this->userForRole($email, $role);

        // Do not reveal whether an email/account exists.
        // if (!$user) {
        //     return $this->response(
        //         true,
        //         'If the email is registered for this account type, an OTP will be sent.'
        //     );
        // }
        if (!$user) {
            return $this->response(
                false,
                'Email does not exist.',
                null,
                404
            );
        }

        $otp = (string) random_int(100000, 999999);

        // Replace any previous OTP for this email and role.
        Cache::put($prefix, [
            'user_id' => $user->id,
            'otp_hash' => Hash::make($otp),
            'role' => $role,
        ], self::OTP_TTL);

        Cache::forget($prefix . ':attempts');

        try {
            Mail::raw(
                "Your Blose password reset OTP is: {$otp}\n\n"
                . "This OTP expires in 5 minutes. Do not share it with anyone.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Blose Password Reset OTP');
                }
            );
        } catch (\Throwable $e) {
            Cache::forget($prefix);
            Cache::forget($prefix . ':attempts');
            Cache::forget($throttleKey);

            report($e);

            return $this->response(
                false,
                'Unable to send OTP right now. Please try again later.',
                null,
                500
            );
        }

        return $this->response(
            true,
            'If the email is registered for this account type, an OTP will be sent.'
        );
    }

    /**
     * Step 2: Verify OTP and issue a short-lived reset token.
     * POST /api/forgot-password/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:buyer,seller,admin'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));
        $role = $validated['role'];
        $prefix = $this->cachePrefix($email, $role);

        $record = Cache::get($prefix);

        if (!$record || ($record['role'] ?? null) !== $role) {
            return $this->response(
                false,
                'OTP is invalid or expired.',
                null,
                422
            );
        }

        $attemptsKey = $prefix . ':attempts';

        // Keep the attempt counter's original expiry.
        Cache::add($attemptsKey, 0, self::OTP_TTL);
        $attempts = Cache::increment($attemptsKey);

        if ($attempts > self::MAX_ATTEMPTS) {
            Cache::forget($prefix);
            Cache::forget($attemptsKey);

            return $this->response(
                false,
                'Too many attempts. Please request a new OTP.',
                null,
                429
            );
        }

        if (!Hash::check($validated['otp'], $record['otp_hash'])) {
            return $this->response(
                false,
                'OTP is invalid or expired.',
                null,
                422
            );
        }

        $user = User::find($record['user_id']);

        if (!$user || !$user->hasRole($role)) {
            Cache::forget($prefix);
            Cache::forget($attemptsKey);

            return $this->response(
                false,
                'Unable to verify this account.',
                null,
                422
            );
        }

        // Consume OTP immediately; it cannot be used again.
        Cache::forget($prefix);
        Cache::forget($attemptsKey);

        $resetToken = Str::random(64);

        Cache::put(
            'password_reset_grant:' . hash('sha256', $resetToken),
            [
                'user_id' => $user->id,
                'role' => $role,
            ],
            self::RESET_TTL
        );

        return $this->response(true, 'OTP verified successfully.', [
            'reset_token' => $resetToken,
            'expires_in' => self::RESET_TTL,
        ]);
    }

    /**
     * Step 3: Set new password using the verified reset token.
     * POST /api/forgot-password/reset
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:buyer,seller,admin'],
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        $grantKey = 'password_reset_grant:' . hash(
            'sha256',
            $validated['reset_token']
        );

        $grant = Cache::get($grantKey);

        if (!$grant || ($grant['role'] ?? null) !== $validated['role']) {
            return $this->response(
                false,
                'Reset token is invalid or expired. Please verify your OTP again.',
                null,
                422
            );
        }

        $user = User::find($grant['user_id']);

        if (!$user || !$user->hasRole($validated['role'])) {
            Cache::forget($grantKey);

            return $this->response(
                false,
                'Unable to reset password for this account.',
                null,
                422
            );
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        // A reset grant can be used only once.
        Cache::forget($grantKey);

        // Revoke existing Sanctum API tokens, if the model supports them.
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return $this->response(
            true,
            'Password reset successfully. Please log in using your new password.'
        );
    }
}
