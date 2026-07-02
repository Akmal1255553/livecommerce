<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * @extends BaseEloquentRepository<Category>
 */
class CategoryRepository extends BaseEloquentRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function listActiveTree(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function findActiveById(int $id): ?Category
    {
        /** @var Category|null */
        return $this->model->newQuery()
            ->where('is_active', true)
            ->find($id);
    }
}
