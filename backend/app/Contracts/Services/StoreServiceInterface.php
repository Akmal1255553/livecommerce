<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Store\SellerAnalyticsSummaryData;
use App\DTOs\Store\SellerDashboardData;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StoreServiceInterface
{
    /**
     * @param  array{store_name: string, description?: string|null, category_id?: int|null, slug?: string|null}  $payload
     */
    public function apply(User $user, array $payload): Store;

    public function getBySlug(string $slug): Store;

    public function dashboard(Store $store): SellerDashboardData;

    public function analyticsSummary(Store $store, string $period = 'last_30_days'): SellerAnalyticsSummaryData;

    /**
     * @return LengthAwarePaginator<int, \App\Models\Product>
     */
    public function listPublicProducts(Store $store, int $page, int $perPage): LengthAwarePaginator;
}
