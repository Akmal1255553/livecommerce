<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateCatalog(int $page, int $perPage, ?string $sortBy, string $sortOrder): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateByCategory(int $categoryId, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateByStore(Store $store, int $page, int $perPage, bool $sellerView = false): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<int, Product>
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
    ): LengthAwarePaginator;

    public function findCatalogProduct(string $id): ?Product;

    public function findStoreProduct(Store $store, string $id): ?Product;

    /**
     * @param  list<int>  $categoryIds
     * @return Collection<int, Product>
     */
    public function listActiveByCategoryIds(array $categoryIds, int $limit): Collection;

    /**
     * @param  list<string>  $productIds
     * @return Collection<int, Product>
     */
    public function findActiveByStoreUser(string $userId, array $productIds): Collection;
}
