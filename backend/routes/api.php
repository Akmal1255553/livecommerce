<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth.api')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth.api')->group(function (): void {
        Route::get('me', [UserController::class, 'show']);
        Route::put('me', [UserController::class, 'update']);
        Route::put('me/notification-settings', [UserController::class, 'updateNotificationSettings']);
        Route::put('me/password', [UserController::class, 'changePassword']);
        Route::delete('me', [UserController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);

        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{token}', [DeviceController::class, 'destroy']);

        Route::post('users/{id}/follow', [UserController::class, 'follow']);
        Route::delete('users/{id}/follow', [UserController::class, 'unfollow']);
    });

    Route::middleware('auth.api.optional')->group(function (): void {
        Route::get('users/{id}', [UserController::class, 'showPublic']);
        Route::get('users/{id}/followers', [UserController::class, 'followers']);
        Route::get('users/{id}/following', [UserController::class, 'following']);
    });
});
