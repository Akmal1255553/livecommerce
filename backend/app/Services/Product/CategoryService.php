<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Logging\StructuredLogger;
use App\Models\Category;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService extends BaseService
{
    public const TREE_CACHE_KEY = 'categories:tree:active';

    public function __construct(
        StructuredLogger $logger,
        private readonly CategoryRepositoryInterface $categories,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        /** @var Collection<int, Category> */
        return Cache::remember(
            self::TREE_CACHE_KEY,
            (int) config('catalog.cache.categories_tree_ttl', 600),
            fn (): Collection => $this->categories->listActiveTree(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        /** @var Category $category */
        $category = Category::query()->create($data);
        $this->forgetTreeCache();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        $this->forgetTreeCache();

        return $category->fresh() ?? $category;
    }

    public function deactivate(Category $category): void
    {
        $category->update(['is_active' => false]);
        $this->forgetTreeCache();
    }

    public function forgetTreeCache(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }
}
