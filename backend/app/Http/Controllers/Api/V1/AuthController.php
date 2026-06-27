<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->validated(),
            $request->input('device_id'),
        );

        return ApiResponse::created([
            'user' => new UserResource($result->user),
            'access_token' => $result->accessToken,
            'refresh_token' => $result->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('login')->toString(),
            $request->string('password')->toString(),
            $request->input('device_id'),
        );

        return ApiResponse::success([
            'user' => new UserResource($result->user),
            'access_token' => $result->accessToken,
            'refresh_token' => $result->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn,
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyOtp(
            $request->string('phone')->toString(),
            $request->string('otp')->toString(),
            $request->input('device_id'),
        );

        return ApiResponse::success([
            'user' => new UserResource($result->user),
            'access_token' => $result->accessToken,
            'refresh_token' => $result->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn,
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $data = $this->authService->resendOtp($request->string('phone')->toString());

        return ApiResponse::success($data);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->authService->refresh(
            $request->string('refresh_token')->toString(),
            $request->input('device_id'),
        );

        return ApiResponse::success($tokens->toArray());
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->string('refresh_token')->toString(),
        );

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->string('email')->toString());

        return ApiResponse::success(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return ApiResponse::success(['message' => 'Password reset successfully.']);
    }
}
