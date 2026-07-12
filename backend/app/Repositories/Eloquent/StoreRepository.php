<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;

/**
 * @extends BaseEloquentRepository<Store>
 */
class StoreRepository extends BaseEloquentRepository implements StoreRepositoryInterface
{
    public function __construct(Store $model)
    {
        parent::__construct($model);
    }

    public function findByUserId(string $userId): ?Store
    {
        /** @var Store|null */
        return $this->model->newQuery()->where('user_id', $userId)->first();
    }

    public function findActiveByUser(User $user): ?Store
    {
        /** @var Store|null */
        return $this->model->newQuery()
            ->where('user_id', $user->id)
            ->where('status', StoreStatus::Active)
            ->first();
    }

    public function findBySlug(string $slug): ?Store
    {
        /** @var Store|null */
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?Store
    {
        /** @var Store|null */
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->where('status', StoreStatus::Active)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Store
    {
        /** @var Store $store */
        $store = $this->model->newQuery()->create($attributes);

        return $store;
    }
}
