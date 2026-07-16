<?php

declare(strict_types=1);

namespace App\Services\Streaming;

use App\Contracts\Services\StreamingProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class AgoraProvider implements StreamingProviderInterface
{
    public function __construct(
        private readonly string $appId,
        private readonly string $appCertificate,
    ) {
        if ($this->appId === '') {
            throw new RuntimeException('AGORA_APP_ID is required when STREAMING_PROVIDER=agora.');
        }
    }

    public function createChannel(string $sessionId, string $title): array
    {
        return [
            'channel_id' => 'agora_'.Str::lower(Str::substr(str_replace('-', '', $sessionId), 0, 16)),
            'stream_key' => null,
        ];
    }

    public function generatePublisherToken(string $channelId, string $userId): string
    {
        return $this->sign('publisher', $channelId, $userId);
    }

    public function generateSubscriberToken(string $channelId, string $userId): string
    {
        return $this->sign('subscriber', $channelId, $userId);
    }

    public function endChannel(string $channelId): void {}

    public function getViewerCount(string $channelId): int
    {
        return 0;
    }

    public function fetchReplayUrl(string $channelId, string $sessionId): ?string
    {
        // Cloud recording fetch is deferred; fake-compatible stub for Agora env.
        return null;
    }

    private function sign(string $role, string $channelId, string $userId): string
    {
        $payload = [
            'provider' => 'agora',
            'app_id' => $this->appId,
            'role' => $role,
            'channel_id' => $channelId,
            'user_id' => $userId,
            'exp' => now()->addHours(6)->timestamp,
        ];

        $body = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $body, $this->appCertificate !== '' ? $this->appCertificate : $this->appId);

        return $body.'.'.$sig;
    }
}
