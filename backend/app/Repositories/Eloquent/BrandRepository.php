<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseEloquentRepository<Brand>
 */
class BrandRepository extends BaseEloquentRepository implements BrandRepositoryInterface
{
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    public function paginateActive(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }
}
