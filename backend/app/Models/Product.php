<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProductStatus $status
 * @property int $version
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'title',
        'description',
        'price',
        'compare_at_price',
        'sku',
        'stock_quantity',
        'status',
        'rating_avg',
        'review_count',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'stock_quantity' => 'integer',
            'review_count' => 'integer',
            'status' => ProductStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return BelongsToMany<Video, $this>
     */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_products')
            ->withPivot([
                'sort_order',
                'is_featured',
                'starts_at',
                'ends_at',
                'position_x',
                'position_y',
                'product_version',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function discountPercent(): ?int
    {
        if ($this->compare_at_price === null || (float) $this->compare_at_price <= (float) $this->price) {
            return null;
        }

        return (int) round(
            (((float) $this->compare_at_price - (float) $this->price) / (float) $this->compare_at_price) * 100,
        );
    }

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Active && $this->stock_quantity > 0;
    }
}
