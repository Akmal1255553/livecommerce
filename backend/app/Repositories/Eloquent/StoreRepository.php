<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Models\Store;

/**
 * @extends BaseEloquentRepository<Store>
 */
class StoreRepository extends BaseEloquentRepository implements StoreRepositoryInterface
{
    public function __construct(Store $model)
    {
        parent::__construct($model);
    }
}
