<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Mail, Cache};

class SendSellerRegistrationOtp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $email, public string $otp, public string $cacheKey, public string $throttleKey,) {}

    public function handle(): void
    {
        try {
            Mail::raw(
                "Your Blose seller registration OTP is: {$this->otp}\n\n"
                . "This OTP expires in 5 minutes. Do not share it with anyone.",
                function ($message) {
                    $message->to($this->email)->subject('Blose Seller Registration OTP');
                }
            );
        } catch (\Throwable $e) {
            Cache::forget($this->cacheKey);
            Cache::forget($this->cacheKey . ':attempts');
            Cache::forget($this->throttleKey);

            report($e);
            throw $e; // retry ke liye
        }
    }
}
