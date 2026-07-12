<?php

declare(strict_types=1);

namespace App\DTOs\Store;

use App\DTOs\DataTransferObject;

readonly class SellerDashboardData extends DataTransferObject
{
    public function __construct(
        public string $storeId,
        public string $storeName,
        public string $storeSlug,
        public string $storeStatus,
        public int $totalProducts,
        public int $activeProducts,
        public int $lowStockProducts,
        public int $pendingOrders,
        public int $totalOrders,
        public int $totalRevenue,
        public string $currency = 'UZS',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'store' => [
                'id' => $this->storeId,
                'name' => $this->storeName,
                'slug' => $this->storeSlug,
                'status' => $this->storeStatus,
            ],
            'total_products' => $this->totalProducts,
            'active_products' => $this->activeProducts,
            'low_stock_products' => $this->lowStockProducts,
            'pending_orders' => $this->pendingOrders,
            'total_orders' => $this->totalOrders,
            'total_revenue' => $this->totalRevenue,
            'currency' => $this->currency,
        ];
    }
}
