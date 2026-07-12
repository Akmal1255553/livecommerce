<?php

declare(strict_types=1);

namespace App\DTOs\Store;

use App\DTOs\DataTransferObject;

readonly class SellerAnalyticsSummaryData extends DataTransferObject
{
    /**
     * @param  list<array{date: string, orders: int, revenue: int}>  $series
     */
    public function __construct(
        public int $totalRevenue,
        public int $totalOrders,
        public int $pendingOrders,
        public int $totalProducts,
        public string $period,
        public string $currency = 'UZS',
        public array $series = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_orders' => $this->totalOrders,
            'pending_orders' => $this->pendingOrders,
            'total_products' => $this->totalProducts,
            'currency' => $this->currency,
            'period' => $this->period,
            'series' => $this->series,
        ];
    }
}
