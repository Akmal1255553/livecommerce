<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Notification\NotificationData;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => NotificationData::fromArray($this->data ?? [])->toArray(),
            'read_at' => $this->read_at !== null ? $this->read_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
