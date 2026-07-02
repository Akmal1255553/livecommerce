<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Logging\StructuredLogger;
use App\Models\Category;
use App\Services\BaseService;
use Illuminate\Support\Collection;

class CategoryService extends BaseService
{
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
        return $this->categories->listActiveTree();
    }
}
