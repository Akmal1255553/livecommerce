<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BrandRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginateActive(int $page, int $perPage): LengthAwarePaginator;
}
