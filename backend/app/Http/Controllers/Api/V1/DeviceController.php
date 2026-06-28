<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RegisterDeviceRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->notificationService->registerDevice(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::created([
            'id' => $device->id,
            'platform' => $device->platform,
            'device_id' => $device->device_id,
            'is_active' => $device->is_active,
        ]);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->notificationService->unregisterDevice($request->user(), urldecode($token));

        return ApiResponse::success(['message' => 'Device unregistered successfully.']);
    }
}
