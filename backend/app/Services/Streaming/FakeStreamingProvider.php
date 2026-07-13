<?php

declare(strict_types=1);

namespace App\Services\Streaming;

use App\Contracts\Services\StreamingProviderInterface;

class FakeStreamingProvider implements StreamingProviderInterface
{
    public function createChannel(string $sessionId, string $title): array
    {
        return [
            'channel_id' => 'fake-'.$sessionId,
            'stream_key' => 'sk-fake-'.substr(sha1($sessionId.$title), 0, 16),
        ];
    }

    public function generatePublisherToken(string $channelId, string $userId): string
    {
        return 'pub-'.hash('sha256', $channelId.'|'.$userId.'|publisher');
    }

    public function generateSubscriberToken(string $channelId, string $userId): string
    {
        return 'sub-'.hash('sha256', $channelId.'|'.$userId.'|subscriber');
    }

    public function endChannel(string $channelId): void
    {
        // No-op for fake provider.
    }

    public function getViewerCount(string $channelId): int
    {
        return 0;
    }
}
