<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Video;
use App\Models\VideoProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Video */
class VideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, VideoProduct> $productTags */
        $productTags = $this->video_product_tags ?? new Collection;

        return [
            'id' => $this->id,
            'user' => new UserCompactResource($this->whenLoaded('user')),
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'thumbnail_url' => $this->thumbnail_url,
            'duration' => $this->duration,
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'is_bookmarked' => (bool) ($this->is_bookmarked ?? false),
            'products' => VideoProductResource::collection($productTags),
            'status' => $this->status->value,
            'failure_code' => $this->when(
                $this->status->value === 'failed' && $request->user()?->id === $this->user_id,
                $this->failure_code,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
