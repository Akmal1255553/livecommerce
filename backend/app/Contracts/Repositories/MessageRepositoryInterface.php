<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Message;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Message;

    /**
     * @return Collection<int, Message>
     */
    public function listForConversation(string $conversationId, ?string $beforeId, int $limit): Collection;
}
