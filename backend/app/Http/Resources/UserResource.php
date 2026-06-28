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
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $profile !== null ? $profile->display_name : null,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'is_verified' => $this->is_verified,
            'role' => $this->role->value,
            'follower_count' => $profile !== null ? $profile->follower_count : 0,
            'following_count' => $profile !== null ? $profile->following_count : 0,
            'video_count' => $profile !== null ? $profile->video_count : 0,
            'is_following' => $this->when(
                $request->user() !== null
                    && $request->user()->id !== $this->id
                    && array_key_exists('is_following', $this->getAttributes()),
                (bool) $this->getAttribute('is_following'),
            ),
            'locale' => $this->locale,
            'phone_verified' => $this->phone_verified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
