<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VideoProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoProduct extends Model
{
    /** @use HasFactory<VideoProductFactory> */
    use HasFactory;

    protected $fillable = [
        'video_id',
        'product_id',
        'sort_order',
        'is_featured',
        'starts_at',
        'ends_at',
        'position_x',
        'position_y',
        'product_version',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'starts_at' => 'float',
            'ends_at' => 'float',
            'position_x' => 'float',
            'position_y' => 'float',
            'product_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
