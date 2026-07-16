<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LiveSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveSession */
class LiveSessionResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly ?string $publisherToken = null,
        private readonly ?string $subscriberToken = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerCount = $this->viewerMetrics?->current_viewers ?? $this->viewer_count;
        $pinnedRows = $this->relationLoaded('pinnedProducts')
            ? $this->pinnedProducts
            : ($this->relationLoaded('sessionProducts')
                ? $this->sessionProducts->where('is_pinned', true)->sortBy('sort_order')->values()
                : collect());

        $timelineRows = $this->relationLoaded('sessionProducts')
            ? $this->sessionProducts
                ->filter(fn ($row) => $row->offset_seconds !== null)
                ->sortBy('offset_seconds')
                ->values()
            : collect();

        $durationSeconds = null;
        if ($this->started_at !== null && $this->ended_at !== null) {
            $durationSeconds = max(0, (int) $this->started_at->diffInSeconds($this->ended_at));
        }

        return [
            'id' => $this->id,
            'seller' => $this->whenLoaded('seller', fn () => new UserCompactResource($this->seller)),
            'store' => $this->whenLoaded('store', fn () => new StoreCompactResource($this->store)),
            'title' => $this->title,
            'status' => $this->status->value,
            'viewer_count' => $viewerCount,
            'peak_viewers' => $this->viewerMetrics?->peak_viewers,
            'unique_viewers' => $this->viewerMetrics?->unique_viewers,
            'pinned_products' => $pinnedRows->map(function ($row) use ($request) {
                $product = $row->relationLoaded('product') ? $row->product : null;

                return [
                    'product_id' => $row->product_id,
                    'is_pinned' => (bool) $row->is_pinned,
                    'pinned_at' => $row->pinned_at?->toIso8601String(),
                    'offset_seconds' => $row->offset_seconds,
                    'sort_order' => $row->sort_order,
                    'product' => $product !== null
                        ? (new ProductCompactResource($product))->toArray($request)
                        : null,
                ];
            })->values()->all(),
            'product_timeline' => $timelineRows->map(function ($row) use ($request) {
                $product = $row->relationLoaded('product') ? $row->product : null;

                return [
                    'product_id' => $row->product_id,
                    'offset_seconds' => (int) $row->offset_seconds,
                    'product' => $product !== null
                        ? (new ProductCompactResource($product))->toArray($request)
                        : null,
                ];
            })->values()->all(),
            'publisher_token' => $this->publisherToken,
            'subscriber_token' => $this->subscriberToken,
            'channel_id' => $this->channel_id,
            'replay_url' => $this->replay_url,
            'duration_seconds' => $durationSeconds,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
        ];
    }
}
