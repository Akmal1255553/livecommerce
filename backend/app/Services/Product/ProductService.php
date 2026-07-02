<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTOs\Pagination\PaginationData;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Product;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService extends BaseService
{
    public function __construct(
        StructuredLogger $logger,
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Product>, pagination: PaginationData}
     */
    public function list(int $page, int $perPage, ?string $sortBy, string $sortOrder): array
    {
        $paginator = $this->products->paginateCatalog($page, $perPage, $sortBy, $sortOrder);

        return $this->page($paginator);
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Product>, pagination: PaginationData}
     */
    public function listByCategory(int $categoryId, int $page, int $perPage): array
    {
        if ($this->categories->findActiveById($categoryId) === null) {
            throw new ResourceNotFoundException('Category not found.');
        }

        $paginator = $this->products->paginateByCategory($categoryId, $page, $perPage);

        return $this->page($paginator);
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Product>, pagination: PaginationData}
     */
    public function search(
        string $query,
        int $page,
        int $perPage,
        ?int $categoryId,
        ?float $minPrice,
        ?float $maxPrice,
        ?string $sortBy,
        string $sortOrder,
    ): array {
        $paginator = $this->products->search(
            $query,
            $page,
            $perPage,
            $categoryId,
            $minPrice,
            $maxPrice,
            $sortBy,
            $sortOrder,
        );

        return $this->page($paginator);
    }

    public function show(string $id): Product
    {
        $product = $this->products->findCatalogProduct($id);

        if ($product === null) {
            throw new ResourceNotFoundException('Product not found.');
        }

        return $product;
    }

    /**
     * @param  LengthAwarePaginator<int, Product>  $paginator
     * @return array{paginator: LengthAwarePaginator<int, Product>, pagination: PaginationData}
     */
    private function page(LengthAwarePaginator $paginator): array
    {
        return [
            'paginator' => $paginator,
            'pagination' => PaginationData::fromPaginator($paginator),
        ];
    }
}
