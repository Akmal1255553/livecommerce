<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface SmsProviderInterface
{
    public function sendOtp(string $phone, string $otp): void;
}
