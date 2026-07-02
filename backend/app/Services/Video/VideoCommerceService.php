<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\VideoProductRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Services\VideoCommerceServiceInterface;
use App\Enums\ProductStatus;
use App\Enums\VideoStatus;
use App\Events\ProductAttachedToVideo;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Product;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProduct;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VideoCommerceService extends BaseService implements VideoCommerceServiceInterface
{
    /**
     * @var list<VideoStatus>
     */
    private const SYNCABLE_STATUSES = [
        VideoStatus::Processing,
        VideoStatus::Published,
    ];

    public function __construct(
        StructuredLogger $logger,
        private readonly VideoRepositoryInterface $videos,
        private readonly VideoProductRepositoryInterface $videoProducts,
        private readonly ProductRepositoryInterface $products,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return Collection<int, VideoProduct>
     */
    public function listForVideo(string $videoId, ?User $viewer): Collection
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video) {
            throw new ResourceNotFoundException('Video not found.');
        }

        $tags = $this->videoProducts->findByVideoIds([$videoId])
            ->filter(fn (VideoProduct $tag): bool => $this->isVisibleToViewer($tag, $video, $viewer));

        return $tags->values();
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return Collection<int, VideoProduct>
     */
    public function syncForVideo(User $owner, string $videoId, array $attachments): Collection
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video) {
            throw new ResourceNotFoundException('Video not found.');
        }

        if ($video->user_id !== $owner->id) {
            throw new ForbiddenException('You can only tag products on your own videos.');
        }

        if (! in_array($video->status, self::SYNCABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'products' => ['Products can only be attached while the video is processing or published.'],
            ]);
        }

        $maxProducts = (int) config('commerce.video_max_products', 20);

        if (count($attachments) > $maxProducts) {
            throw ValidationException::withMessages([
                'products' => ["A video can have at most {$maxProducts} products."],
            ]);
        }

        $normalized = $this->normalizeAttachments($attachments, $video);
        $rows = $this->buildPivotRows($owner, $video, $normalized);

        DB::transaction(function () use ($videoId, $rows, $video, $owner, $normalized): void {
            $this->videoProducts->syncForVideo($videoId, $rows);

            $productIds = array_column($normalized, 'product_id');
            $featured = collect($normalized)->firstWhere('is_featured', true);

            ProductAttachedToVideo::dispatch(
                $video->id,
                $owner->id,
                $productIds,
                is_array($featured) ? ($featured['product_id'] ?? null) : null,
            );
        });

        return $this->listForVideo($videoId, $owner);
    }

    /**
     * @param  Collection<int, Video>  $videos
     * @return Collection<int, Video>
     */
    public function hydrateForVideos(Collection $videos, ?User $viewer): Collection
    {
        if ($videos->isEmpty()) {
            return $videos;
        }

        $videoIds = $videos->pluck('id')->all();
        $allTags = $this->videoProducts->findByVideoIds($videoIds);
        $tagsByVideo = $allTags->groupBy('video_id');

        return $videos->map(function (Video $video) use ($tagsByVideo, $viewer): Video {
            $tags = $tagsByVideo->get($video->id, collect())
                ->filter(fn (VideoProduct $tag): bool => $this->isVisibleToViewer($tag, $video, $viewer))
                ->values();

            $video->setAttribute('video_product_tags', $tags);

            return $video;
        });
    }

    private function isVisibleToViewer(VideoProduct $tag, Video $video, ?User $viewer): bool
    {
        $product = $tag->product;

        if (! $product instanceof Product) {
            return false;
        }

        if ($viewer !== null && $viewer->id === $video->user_id) {
            return true;
        }

        return $product->status === ProductStatus::Active && $product->isPurchasable();
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return list<array<string, mixed>>
     */
    private function normalizeAttachments(array $attachments, Video $video): array
    {
        $productIds = array_column($attachments, 'product_id');
        $duplicates = array_diff_assoc($productIds, array_unique($productIds));

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'products' => ['Duplicate product_id values are not allowed.'],
            ]);
        }

        $featuredCount = collect($attachments)->where('is_featured', true)->count();

        if ($featuredCount > 1) {
            throw ValidationException::withMessages([
                'products' => ['Only one product can be featured per video.'],
            ]);
        }

        $normalized = [];

        foreach ($attachments as $index => $attachment) {
            $startsAt = $attachment['starts_at'] ?? null;
            $endsAt = $attachment['ends_at'] ?? null;

            if ($startsAt !== null && $endsAt !== null && (float) $startsAt >= (float) $endsAt) {
                throw ValidationException::withMessages([
                    "products.{$index}.ends_at" => ['ends_at must be greater than starts_at.'],
                ]);
            }

            if ($video->duration !== null) {
                if ($startsAt !== null && (float) $startsAt > (float) $video->duration) {
                    throw ValidationException::withMessages([
                        "products.{$index}.starts_at" => ['starts_at cannot exceed video duration.'],
                    ]);
                }

                if ($endsAt !== null && (float) $endsAt > (float) $video->duration) {
                    throw ValidationException::withMessages([
                        "products.{$index}.ends_at" => ['ends_at cannot exceed video duration.'],
                    ]);
                }
            }

            $normalized[] = [
                'product_id' => $attachment['product_id'],
                'sort_order' => $attachment['sort_order'] ?? $index,
                'is_featured' => (bool) ($attachment['is_featured'] ?? false),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'position_x' => $attachment['position_x'] ?? null,
                'position_y' => $attachment['position_y'] ?? null,
            ];
        }

        if ($normalized !== [] && ! collect($normalized)->contains('is_featured', true)) {
            $normalized[0]['is_featured'] = true;
        }

        usort($normalized, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $normalized
     * @return list<array<string, mixed>>
     */
    private function buildPivotRows(User $owner, Video $video, array $normalized): array
    {
        if ($normalized === []) {
            return [];
        }

        $productIds = array_column($normalized, 'product_id');
        $products = $this->products->findActiveByStoreUser($owner->id, $productIds);

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages([
                'products' => ['All products must be active and belong to your store.'],
            ]);
        }

        $productsById = $products->keyBy('id');
        $rows = [];

        foreach ($normalized as $attachment) {
            /** @var Product $product */
            $product = $productsById->get($attachment['product_id']);

            $rows[] = [
                'product_id' => $product->id,
                'sort_order' => $attachment['sort_order'],
                'is_featured' => $attachment['is_featured'],
                'starts_at' => $attachment['starts_at'],
                'ends_at' => $attachment['ends_at'],
                'position_x' => $attachment['position_x'],
                'position_y' => $attachment['position_y'],
                'product_version' => $product->version,
            ];
        }

        return $rows;
    }
}
