<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Services\SmsProviderInterface;

class StubSmsProvider implements SmsProviderInterface
{
    public function sendOtp(string $phone, string $otp): void
    {
        // Sprint 1 stub — integrate real SMS provider in production.
        logger()->info('SMS OTP stub', [
            'phone' => $phone,
            'otp' => app()->environment('production') ? '[redacted]' : $otp,
        ]);
    }
}
