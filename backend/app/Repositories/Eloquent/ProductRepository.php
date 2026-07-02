<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseEloquentRepository<Product>
 */
class ProductRepository extends BaseEloquentRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        /** @var Product $product */
        $product = $this->model->newQuery()->create($attributes);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $product->fill($attributes);
        $product->save();

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function paginateCatalog(int $page, int $perPage, ?string $sortBy, string $sortOrder): LengthAwarePaginator
    {
        $query = $this->catalogQuery()
            ->with($this->catalogRelations());

        $this->applySort($query, $sortBy, $sortOrder);

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function paginateByCategory(int $categoryId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->catalogQuery()
            ->where('category_id', $categoryId)
            ->with($this->catalogRelations())
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function paginateByStore(Store $store, int $page, int $perPage, bool $sellerView = false): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->where('store_id', $store->id)
            ->with($this->catalogRelations());

        if (! $sellerView) {
            $query->where('status', ProductStatus::Active);
        }

        return $query->orderByDesc('created_at')->paginate(perPage: $perPage, page: $page);
    }

    public function search(
        string $query,
        int $page,
        int $perPage,
        ?int $categoryId,
        ?float $minPrice,
        ?float $maxPrice,
        ?string $sortBy,
        string $sortOrder,
    ): LengthAwarePaginator {
        $builder = $this->catalogQuery()
            ->with($this->catalogRelations())
            ->where(function (Builder $nested) use ($query): void {
                $nested->where('title', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%')
                    ->orWhere('sku', 'like', '%'.$query.'%');
            });

        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        if ($minPrice !== null) {
            $builder->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $builder->where('price', '<=', $maxPrice);
        }

        $this->applySort($builder, $sortBy, $sortOrder);

        return $builder->paginate(perPage: $perPage, page: $page);
    }

    public function findCatalogProduct(string $id): ?Product
    {
        /** @var Product|null */
        return $this->catalogQuery()
            ->with($this->catalogRelations())
            ->find($id);
    }

    public function findStoreProduct(Store $store, string $id): ?Product
    {
        /** @var Product|null */
        return $this->model->newQuery()
            ->where('store_id', $store->id)
            ->with($this->catalogRelations())
            ->find($id);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function listActiveByCategoryIds(array $categoryIds, int $limit): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        /** @var Collection<int, Product> */
        return $this->catalogQuery()
            ->whereIn('category_id', $categoryIds)
            ->with(['store', 'images'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<string>  $productIds
     */
    public function findActiveByStoreUser(string $userId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        /** @var Collection<int, Product> */
        return $this->model->newQuery()
            ->whereIn('id', $productIds)
            ->where('status', ProductStatus::Active)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $userId))
            ->get();
    }

    /**
     * @return Builder<Product>
     */
    private function catalogQuery(): Builder
    {
        return $this->model->newQuery()->where('status', ProductStatus::Active);
    }

    /**
     * @return list<string>
     */
    private function catalogRelations(): array
    {
        return ['store', 'category', 'brand', 'images', 'variants'];
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, ?string $sortBy, string $sortOrder): void
    {
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'price' => $query->orderBy('price', $direction),
            'title' => $query->orderBy('title', $direction),
            'rating' => $query->orderBy('rating_avg', $direction),
            default => $query->orderByDesc('created_at'),
        };
    }
}
