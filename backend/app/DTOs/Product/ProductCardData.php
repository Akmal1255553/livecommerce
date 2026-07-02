<?php

declare(strict_types=1);

namespace App\DTOs\Product;

use App\Models\Product;

final readonly class ProductCardData
{
    public function __construct(
        public string $id,
        public string $title,
        public float $price,
        public ?float $compareAtPrice,
        public ?int $discountPercent,
        public string $currency,
        public ?string $thumbnail,
        public ?string $storeName,
        public string $status,
        public bool $isPurchasable,
    ) {}

    public static function fromProduct(Product $product): self
    {
        $thumbnail = $product->relationLoaded('images')
            ? $product->images->first()?->url
            : null;

        $storeName = $product->relationLoaded('store')
            ? $product->store?->name
            : null;

        return new self(
            id: $product->id,
            title: $product->title,
            price: (float) $product->price,
            compareAtPrice: $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            discountPercent: $product->discountPercent(),
            currency: 'UZS',
            thumbnail: $thumbnail,
            storeName: $storeName,
            status: $product->status->value,
            isPurchasable: $product->isPurchasable(),
        );
    }
}
