<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->profile?->display_name,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_verified' => $this->is_verified,
            'role' => $this->role->value,
            'follower_count' => $this->profile?->follower_count ?? 0,
            'following_count' => $this->profile?->following_count ?? 0,
            'video_count' => $this->profile?->video_count ?? 0,
            'locale' => $this->locale,
            'phone_verified' => $this->phone_verified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
