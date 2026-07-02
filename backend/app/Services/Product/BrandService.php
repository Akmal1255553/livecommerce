<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\BrandRepositoryInterface;
use App\DTOs\Pagination\PaginationData;
use App\Logging\StructuredLogger;
use App\Models\Brand;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BrandService extends BaseService
{
    public function __construct(
        StructuredLogger $logger,
        private readonly BrandRepositoryInterface $brands,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Brand>, pagination: PaginationData}
     */
    public function list(int $page, int $perPage): array
    {
        $paginator = $this->brands->paginateActive($page, $perPage);

        return [
            'paginator' => $paginator,
            'pagination' => PaginationData::fromPaginator($paginator),
        ];
    }
}
