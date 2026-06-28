<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserCompactResource($this->whenLoaded('user')),
            'body' => $this->body,
            'like_count' => $this->like_count,
            'parent_id' => $this->parent_id,
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
