<?php

declare(strict_types=1);

namespace App\DTOs\Live;

use App\DTOs\DataTransferObject;

readonly class SellerLiveAnalyticsOverviewData extends DataTransferObject
{
    /**
     * @param  list<array<string, mixed>>  $sessions
     */
    public function __construct(
        public int $totalSessions,
        public int $totalUniqueViewers,
        public int $totalPeakViewers,
        public int $totalAddToCart,
        public int $totalPins,
        public float $avgCartConversionRate,
        public array $sessions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_sessions' => $this->totalSessions,
            'total_unique_viewers' => $this->totalUniqueViewers,
            'total_peak_viewers' => $this->totalPeakViewers,
            'total_add_to_cart' => $this->totalAddToCart,
            'total_pins' => $this->totalPins,
            'avg_cart_conversion_rate' => $this->avgCartConversionRate,
            'sessions' => $this->sessions,
        ];
    }
}
