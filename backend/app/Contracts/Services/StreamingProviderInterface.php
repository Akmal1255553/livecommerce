<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface StreamingProviderInterface
{
    /**
     * @return array{channel_id: string, stream_key: string|null}
     */
    public function createChannel(string $sessionId, string $title): array;

    public function generatePublisherToken(string $channelId, string $userId): string;

    public function generateSubscriberToken(string $channelId, string $userId): string;

    public function endChannel(string $channelId): void;

    public function getViewerCount(string $channelId): int;
}
