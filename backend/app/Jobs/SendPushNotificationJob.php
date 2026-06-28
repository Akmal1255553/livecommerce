<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\Contracts\Services\PushNotificationInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(
        PushNotificationInterface $push,
        UserDeviceRepositoryInterface $devices,
    ): void {
        foreach ($devices->activeTokensForUser($this->userId) as $token) {
            $push->send($token, $this->title, $this->body, $this->data);
        }
    }
}
