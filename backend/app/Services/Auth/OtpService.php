<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Services\SmsProviderInterface;
use App\Exceptions\Domain\ConflictException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OtpService
{
    private const OTP_TTL_SECONDS = 300;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private readonly SmsProviderInterface $smsProvider) {}

    public function send(string $phone): int
    {
        $cooldownKey = $this->cooldownKey($phone);

        if (Cache::has($cooldownKey)) {
            $retryAfter = (int) Cache::get($cooldownKey);

            throw new ConflictException("OTP already sent. Retry after {$retryAfter} seconds.");
        }

        $otp = app()->environment('testing') ? '123456' : (string) random_int(100000, 999999);

        Cache::put($this->otpKey($phone), $otp, self::OTP_TTL_SECONDS);
        Cache::put($cooldownKey, self::RESEND_COOLDOWN_SECONDS, self::RESEND_COOLDOWN_SECONDS);

        $this->smsProvider->sendOtp($phone, $otp);

        return self::RESEND_COOLDOWN_SECONDS;
    }

    public function verify(string $phone, string $otp): bool
    {
        $stored = Cache::get($this->otpKey($phone));

        if (! is_string($stored) || ! hash_equals($stored, $otp)) {
            return false;
        }

        Cache::forget($this->otpKey($phone));

        return true;
    }

    private function otpKey(string $phone): string
    {
        return 'otp:'.Str::slug($phone, '');
    }

    private function cooldownKey(string $phone): string
    {
        return 'otp_sent:'.Str::slug($phone, '');
    }
}
