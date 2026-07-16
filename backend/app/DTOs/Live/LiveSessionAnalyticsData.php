<?php

declare(strict_types=1);

namespace App\DTOs\Live;

use App\DTOs\DataTransferObject;

readonly class LiveSessionAnalyticsData extends DataTransferObject
{
    /**
     * @param  list<array{product_id: string, title: string|null, pins: int, add_to_cart: int}>  $topProducts
     * @param  array<string, int>  $eventCounts
     */
    public function __construct(
        public string $sessionId,
        public string $title,
        public string $status,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?int $durationSeconds,
        public int $peakViewers,
        public int $uniqueViewers,
        public int $currentViewers,
        public int $chatMessages,
        public int $productsPinned,
        public int $productsAddedToCart,
        public float $cartConversionRate,
        public array $eventCounts,
        public array $topProducts,
        public ?string $replayUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'title' => $this->title,
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'ended_at' => $this->endedAt,
            'duration_seconds' => $this->durationSeconds,
            'peak_viewers' => $this->peakViewers,
            'unique_viewers' => $this->uniqueViewers,
            'current_viewers' => $this->currentViewers,
            'chat_messages' => $this->chatMessages,
            'products_pinned' => $this->productsPinned,
            'products_added_to_cart' => $this->productsAddedToCart,
            'cart_conversion_rate' => $this->cartConversionRate,
            'event_counts' => $this->eventCounts,
            'top_products' => $this->topProducts,
            'replay_url' => $this->replayUrl,
        ];
    }
}
