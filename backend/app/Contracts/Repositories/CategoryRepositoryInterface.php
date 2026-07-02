<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, Category>
     */
    public function listActiveTree(): Collection;

    public function findActiveById(int $id): ?Category;
}
