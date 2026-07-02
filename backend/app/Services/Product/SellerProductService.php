<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTOs\Pagination\PaginationData;
use App\Enums\ProductStatus;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Product;
use App\Models\Store;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SellerProductService extends BaseService
{
    public function __construct(
        StructuredLogger $logger,
        private readonly ProductRepositoryInterface $products,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Product>, pagination: PaginationData}
     */
    public function list(Store $store, int $page, int $perPage): array
    {
        $paginator = $this->products->paginateByStore($store, $page, $perPage, sellerView: true);

        return [
            'paginator' => $paginator,
            'pagination' => PaginationData::fromPaginator($paginator),
        ];
    }

    public function show(Store $store, string $id): Product
    {
        $product = $this->products->findStoreProduct($store, $id);

        if ($product === null) {
            throw new ResourceNotFoundException('Product not found.');
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Store $store, array $data): Product
    {
        return DB::transaction(function () use ($store, $data): Product {
            $status = $this->resolveStatus(
                $data['status'] ?? ProductStatus::Draft,
                (int) $data['stock_quantity'],
            );

            $product = $this->products->create([
                'store_id' => $store->id,
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'sku' => $data['sku'] ?? null,
                'stock_quantity' => $data['stock_quantity'],
                'status' => $status,
            ]);

            $this->syncImages($product, $data['images'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);

            $this->logger->info('product.created', [
                'product_id' => $product->id,
                'store_id' => $store->id,
            ]);

            return $product->fresh(['store', 'category', 'brand', 'images', 'variants']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Store $store, string $id, array $data): Product
    {
        $product = $this->show($store, $id);

        return DB::transaction(function () use ($product, $data): Product {
            $attributes = [];

            foreach (['category_id', 'title', 'description', 'price', 'compare_at_price', 'sku', 'stock_quantity'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (array_key_exists('brand_id', $data)) {
                $attributes['brand_id'] = $data['brand_id'];
            }

            $stockQuantity = (int) ($data['stock_quantity'] ?? $product->stock_quantity);

            if (array_key_exists('status', $data)) {
                $attributes['status'] = $this->resolveStatus($data['status'], $stockQuantity);
            } elseif (array_key_exists('stock_quantity', $data)) {
                $attributes['status'] = $this->resolveStatus($product->status, $stockQuantity);
            }

            if ($attributes !== []) {
                $product = $this->products->update($product, $attributes);
            }

            if (array_key_exists('images', $data)) {
                $this->syncImages($product, $data['images'] ?? []);
            }

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants'] ?? []);
            }

            if ($attributes !== [] || array_key_exists('images', $data) || array_key_exists('variants', $data)) {
                $product = $this->products->update($product, [
                    'version' => $product->version + 1,
                ]);
            }

            $this->logger->info('product.updated', ['product_id' => $product->id]);

            return $product->fresh(['store', 'category', 'brand', 'images', 'variants']);
        });
    }

    public function delete(Store $store, string $id): void
    {
        $product = $this->show($store, $id);
        $this->products->delete($product);

        $this->logger->info('product.deleted', ['product_id' => $product->id]);
    }

    private function resolveStatus(ProductStatus|string $status, int $stockQuantity): ProductStatus
    {
        $resolved = $status instanceof ProductStatus ? $status : ProductStatus::from($status);

        if ($stockQuantity <= 0) {
            return ProductStatus::OutOfStock;
        }

        if ($resolved === ProductStatus::OutOfStock) {
            return ProductStatus::Active;
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>|string>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach ($images as $index => $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : $image;
            if (! is_string($url) || $url === '') {
                continue;
            }

            $product->images()->create([
                'url' => $url,
                'sort_order' => is_array($image) ? (int) ($image['sort_order'] ?? $index) : $index,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();

        foreach ($variants as $variant) {
            $product->variants()->create([
                'name' => $variant['name'],
                'value' => $variant['value'],
                'sku' => $variant['sku'] ?? null,
                'price_adjustment' => $variant['price_adjustment'] ?? 0,
                'stock_quantity' => $variant['stock_quantity'] ?? 0,
            ]);
        }
    }
}
