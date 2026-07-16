<?php

declare(strict_types=1);

namespace App\DTOs\Live;

use App\DTOs\DataTransferObject;

readonly class LiveAssistantSuggestionData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $priority,
        public string $title,
        public string $body,
        public ?string $action = null,
        public array $payload = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'priority' => $this->priority,
            'title' => $this->title,
            'body' => $this->body,
            'action' => $this->action,
            'payload' => $this->payload,
        ];
    }
}
