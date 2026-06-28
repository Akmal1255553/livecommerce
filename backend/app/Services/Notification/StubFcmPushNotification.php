<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\Services\PushNotificationInterface;

class StubFcmPushNotification implements PushNotificationInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): void
    {
        logger()->info('FCM push stub', [
            'token' => app()->environment('production') ? '[redacted]' : $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
