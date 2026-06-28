<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Auth\AuthResultData;
use App\DTOs\Auth\TokenPairData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\UserRegistered;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService extends BaseService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly JwtService $jwt,
        private readonly OtpService $otp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?string $deviceId = null): AuthResultData
    {
        if ($this->users->findByUsername($data['username'])) {
            throw new ConflictException('Username is already taken.');
        }

        if (! empty($data['email']) && $this->users->findByEmail($data['email'])) {
            throw new ConflictException('Email is already registered.');
        }

        if (! empty($data['phone']) && $this->users->findByPhone($data['phone'])) {
            throw new ConflictException('Phone number is already registered.');
        }

        $user = $this->users->create([
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::User,
            'status' => UserStatus::Active,
            'locale' => $data['locale'] ?? 'uz',
            'email_verified_at' => ! empty($data['email']) ? now() : null,
            'phone_verified_at' => ! empty($data['phone']) ? null : null,
        ]);

        event(new UserRegistered($user));

        if (! empty($data['phone'])) {
            $this->otp->send($data['phone']);
        }

        return $this->issueAuthResult($user, $deviceId);
    }

    public function login(string $login, string $password, ?string $deviceId = null): AuthResultData
    {
        $user = $this->users->findByLogin($login);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new UnauthorizedException('Invalid credentials.');
        }

        $this->ensureUserCanAuthenticate($user);

        return $this->issueAuthResult($user, $deviceId);
    }

    public function verifyOtp(string $phone, string $otp, ?string $deviceId = null): AuthResultData
    {
        if (! $this->otp->verify($phone, $otp)) {
            throw new UnauthorizedException('Invalid or expired OTP.');
        }

        $user = $this->users->findByPhone($phone);

        if ($user === null) {
            throw new ResourceNotFoundException('User not found for this phone number.');
        }

        $this->ensureUserCanAuthenticate($user);

        $user->update(['phone_verified_at' => now()]);

        return $this->issueAuthResult($user->fresh(), $deviceId);
    }

    /**
     * @return array{message: string, retry_after: int}
     */
    public function resendOtp(string $phone): array
    {
        $user = $this->users->findByPhone($phone);

        if ($user === null) {
            throw new ResourceNotFoundException('User not found for this phone number.');
        }

        $retryAfter = $this->otp->send($phone);

        return [
            'message' => 'OTP sent successfully.',
            'retry_after' => $retryAfter,
        ];
    }

    public function refresh(string $refreshToken, ?string $deviceId = null): TokenPairData
    {
        $hash = $this->jwt->hashRefreshToken($refreshToken);
        $stored = $this->refreshTokens->findValidByHash($hash);

        if ($stored === null) {
            throw new UnauthorizedException('Invalid or expired refresh token.');
        }

        $user = $stored->user;

        if ($user === null || ! $user->isActive()) {
            throw new UnauthorizedException('Invalid or expired refresh token.');
        }

        $this->refreshTokens->revoke($stored);

        return $this->issueTokenPair($user, $deviceId);
    }

    public function logout(User $user, string $refreshToken): void
    {
        $hash = $this->jwt->hashRefreshToken($refreshToken);
        $stored = $this->refreshTokens->findValidByHash($hash);

        if ($stored !== null && $stored->user_id === $user->id) {
            $this->refreshTokens->revoke($stored);
        }
    }

    public function forgotPassword(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        logger()->info('Password reset token generated', [
            'email' => $email,
            'token' => app()->environment('production') ? '[redacted]' : $token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resetPassword(array $data): void
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if ($record === null || ! Hash::check($data['token'], $record->token)) {
            throw new UnauthorizedException('Invalid or expired reset token.');
        }

        $createdAt = $record->created_at ? strtotime((string) $record->created_at) : false;

        if ($createdAt === false || $createdAt < now()->subHour()->timestamp) {
            throw new UnauthorizedException('Invalid or expired reset token.');
        }

        $user = $this->users->findByEmail($data['email']);

        if ($user === null) {
            throw new ResourceNotFoundException('User not found.');
        }

        $user->update(['password' => $data['password']]);
        $this->refreshTokens->revokeAllForUser($user->id);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
    }

    private function issueAuthResult(User $user, ?string $deviceId): AuthResultData
    {
        $tokens = $this->issueTokenPair($user, $deviceId);

        return new AuthResultData(
            user: $user->loadMissing('profile'),
            accessToken: $tokens->accessToken,
            refreshToken: $tokens->refreshToken,
            expiresIn: $tokens->expiresIn,
        );
    }

    private function issueTokenPair(User $user, ?string $deviceId): TokenPairData
    {
        $accessToken = $this->jwt->issueAccessToken($user);
        $refreshToken = $this->jwt->generateRefreshToken();
        $expiresAt = now()->addMinutes($this->jwt->getRefreshTtlMinutes());

        $this->refreshTokens->create(
            $user->id,
            $this->jwt->hashRefreshToken($refreshToken),
            $deviceId,
            $expiresAt,
        );

        return new TokenPairData(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: $this->jwt->getExpiresInSeconds(),
        );
    }

    private function ensureUserCanAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new UnauthorizedException('Account is not active.');
        }
    }
}
