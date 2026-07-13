<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ContentType;
use App\Models\LiveSession;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentItemResource extends JsonResource
{
    public function __construct(
        private readonly ContentType|string $contentType,
        private readonly Video|LiveSession|array $payload,
    ) {
        $type = $contentType instanceof ContentType ? $contentType : ContentType::from((string) $contentType);
        $id = match (true) {
            $payload instanceof Video, $payload instanceof LiveSession => (string) $payload->id,
            default => (string) ($payload['id'] ?? ''),
        };

        parent::__construct([
            'type' => $type,
            'id' => $id,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ContentType $type */
        $type = $this->resource['type'];
        $payload = $this->resource['payload'];

        return [
            'type' => $type->value,
            'id' => (string) $this->resource['id'],
            'payload' => match ($type) {
                ContentType::Live => $payload instanceof LiveSession
                    ? (new LiveSessionResource($payload))->toArray($request)
                    : $payload,
                ContentType::Video => $payload instanceof Video
                    ? (new VideoResource($payload))->toArray($request)
                    : $payload,
            },
        ];
    }
}
