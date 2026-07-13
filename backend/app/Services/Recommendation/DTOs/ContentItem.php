<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;
use App\Enums\ContentType;
use App\Models\LiveSession;
use App\Models\Video;

readonly class ContentItem extends DataTransferObject
{
    public function __construct(
        public ContentType $type,
        public Video|LiveSession $payload,
    ) {}
}
