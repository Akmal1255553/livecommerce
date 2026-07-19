<?php

declare(strict_types=1);

namespace App\Services\Streaming;

use App\Contracts\Services\StreamingProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class AgoraProvider implements StreamingProviderInterface
{
    private const TOKEN_TTL_SECONDS = 21600;

    public function __construct(
        private readonly string $appId,
        private readonly string $appCertificate,
    ) {
        if ($this->appId === '') {
            throw new RuntimeException('AGORA_APP_ID is required when STREAMING_PROVIDER=agora.');
        }
        if ($this->appCertificate === '') {
            throw new RuntimeException('AGORA_APP_CERTIFICATE is required when STREAMING_PROVIDER=agora.');
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
        return $this->buildToken($channelId, $userId, 1); // ROLE_PUBLISHER
    }

    public function generateSubscriberToken(string $channelId, string $userId): string
    {
        return $this->buildToken($channelId, $userId, 2); // ROLE_SUBSCRIBER
    }

    public function endChannel(string $channelId): void {}

    public function getViewerCount(string $channelId): int
    {
        return 0;
    }

    public function fetchReplayUrl(string $channelId, string $sessionId): ?string
    {
        // Cloud recording fetch is deferred.
        return null;
    }

    public function appId(): string
    {
        return $this->appId;
    }

    private function buildToken(string $channelId, string $userId, int $role): string
    {
        // uid 0 = any client uid may join (matches Flutter joinChannel uid: 0).
        unset($userId);

        require_once base_path('third_party/agora/RtcTokenBuilder2.php');

        /** @var string $token */
        $token = \RtcTokenBuilder2::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channelId,
            0,
            $role,
            self::TOKEN_TTL_SECONDS,
            self::TOKEN_TTL_SECONDS,
        );

        if ($token === '') {
            throw new RuntimeException('Failed to build Agora RTC token. Check AGORA_APP_ID / AGORA_APP_CERTIFICATE.');
        }

        return $token;
    }
}
