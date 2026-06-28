<?php

declare(strict_types=1);

namespace App\DTOs\Pagination;

use App\DTOs\DataTransferObject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class PaginationData extends DataTransferObject
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
        );
    }
}
