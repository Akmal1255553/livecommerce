<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LiveSessionRepositoryInterface;
use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use Illuminate\Support\Collection;

/**
 * @extends BaseEloquentRepository<LiveSession>
 */
class LiveSessionRepository extends BaseEloquentRepository implements LiveSessionRepositoryInterface
{
    public function __construct(LiveSession $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): LiveSession
    {
        /** @var LiveSession $session */
        $session = $this->model->newQuery()->create($attributes);

        return $session;
    }

    public function save(LiveSession $session): LiveSession
    {
        $session->save();

        return $session;
    }

    public function listLive(int $limit = 20): Collection
    {
        return $this->model->newQuery()
            ->with([
                'seller.profile',
                'store',
                'viewerMetrics',
                'sessionProducts.product.images',
            ])
            ->where('status', LiveSessionStatus::Live)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function findLiveBySeller(string $sellerId): ?LiveSession
    {
        /** @var LiveSession|null $session */
        $session = $this->model->newQuery()
            ->where('seller_id', $sellerId)
            ->where('status', LiveSessionStatus::Live)
            ->first();

        return $session;
    }

    public function findWithRelations(string $id): ?LiveSession
    {
        /** @var LiveSession|null $session */
        $session = $this->model->newQuery()
            ->with([
                'seller.profile',
                'store',
                'viewerMetrics',
                'pinnedProducts.product.images',
                'sessionProducts' => fn ($q) => $q->where('is_pinned', true)->orderBy('sort_order'),
                'sessionProducts.product.images',
            ])
            ->find($id);

        return $session;
    }
}
