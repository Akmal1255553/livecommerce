<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Live\LiveAddToCartResult;
use App\Models\LiveChatMessage;
use App\Models\LiveSession;
use App\Models\LiveViewerMetric;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

interface LiveSessionServiceInterface
{
    /**
     * @param  array{title: string, product_ids?: list<string>}  $payload
     */
    public function start(User $seller, Store $store, array $payload): LiveSession;

    public function end(User $seller, string $sessionId): LiveSession;

    /**
     * @return Collection<int, LiveSession>
     */
    public function listLive(int $limit = 20): Collection;

    /**
     * @return Collection<int, LiveSession>
     */
    public function listLiveCandidates(int $limit = 100): Collection;

    public function get(string $sessionId): LiveSession;

    public function pinProduct(User $seller, string $sessionId, string $productId): LiveSession;

    public function unpinProduct(User $seller, string $sessionId, string $productId): LiveSession;

    public function sendChat(User $user, string $sessionId, string $message): LiveChatMessage;

    /**
     * @return Collection<int, LiveChatMessage>
     */
    public function listChat(string $sessionId, ?int $afterId = null, int $limit = 50): Collection;

    public function join(User $user, string $sessionId): LiveViewerMetric;

    public function leave(User $user, string $sessionId): LiveViewerMetric;

    public function addToCart(User $user, string $sessionId, string $productId, int $quantity = 1): LiveAddToCartResult;

    public function publisherToken(LiveSession $session, User $user): ?string;

    public function subscriberToken(LiveSession $session, User $user): string;
}
