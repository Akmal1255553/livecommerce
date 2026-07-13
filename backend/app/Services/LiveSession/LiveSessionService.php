<?php

declare(strict_types=1);

namespace App\Services\LiveSession;

use App\Contracts\Repositories\LiveSessionRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\LiveAnalyticsServiceInterface;
use App\Contracts\Services\LiveSessionServiceInterface;
use App\Contracts\Services\StreamingProviderInterface;
use App\Contracts\Services\ViewerMetricsServiceInterface;
use App\Enums\LiveAnalyticsEventType;
use App\Enums\LiveChatMessageType;
use App\Enums\LiveSessionStatus;
use App\Events\LiveSessionEnded;
use App\Events\LiveSessionStarted;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\LiveChatMessage;
use App\Models\LiveSession;
use App\Models\LiveSessionProduct;
use App\Models\LiveViewerMetric;
use App\Models\Store;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveSessionService extends BaseService implements LiveSessionServiceInterface
{
    private const MAX_PINNED = 3;

    public function __construct(
        StructuredLogger $logger,
        private readonly LiveSessionRepositoryInterface $sessions,
        private readonly ProductRepositoryInterface $products,
        private readonly StreamingProviderInterface $streaming,
        private readonly ViewerMetricsServiceInterface $viewerMetrics,
        private readonly LiveAnalyticsServiceInterface $analytics,
    ) {
        parent::__construct($logger);
    }

    public function start(User $seller, Store $store, array $payload): LiveSession
    {
        if ($this->sessions->findLiveBySeller((string) $seller->id) !== null) {
            throw new ConflictException('Seller already has an active live session.');
        }

        $title = trim($payload['title']);
        $productIds = array_values(array_unique($payload['product_ids'] ?? []));

        $session = DB::transaction(function () use ($seller, $store, $title, $productIds): LiveSession {
            $sessionId = (string) Str::uuid();
            $channel = $this->streaming->createChannel($sessionId, $title);

            $session = $this->sessions->create([
                'id' => $sessionId,
                'seller_id' => $seller->id,
                'store_id' => $store->id,
                'title' => $title,
                'channel_id' => $channel['channel_id'],
                'stream_key' => $channel['stream_key'],
                'status' => LiveSessionStatus::Live,
                'viewer_count' => 0,
                'started_at' => now(),
            ]);

            $this->viewerMetrics->initialize((string) $session->id);

            $sort = 0;
            foreach ($productIds as $productId) {
                $product = $this->products->findByIdOrFail($productId);
                if ((string) $product->store_id !== (string) $store->id) {
                    throw new ForbiddenException('Product does not belong to seller store.');
                }

                LiveSessionProduct::query()->create([
                    'live_session_id' => $session->id,
                    'product_id' => $product->id,
                    'is_pinned' => false,
                    'sort_order' => $sort++,
                ]);
            }

            return $session;
        });

        $this->analytics->record((string) $session->id, LiveAnalyticsEventType::LiveStarted, $seller, [
            'title' => $session->title,
        ]);

        event(new LiveSessionStarted($session));

        $this->logger->info('live.session_started', [
            'live_session_id' => $session->id,
            'seller_id' => $seller->id,
        ]);

        return $this->sessions->findWithRelations((string) $session->id) ?? $session;
    }

    public function end(User $seller, string $sessionId): LiveSession
    {
        $session = $this->requireSellerSession($seller, $sessionId);

        if ($session->status !== LiveSessionStatus::Live) {
            throw new ConflictException('Live session is not active.');
        }

        $session->status = LiveSessionStatus::Ended;
        $session->ended_at = now();
        $this->sessions->save($session);

        $this->streaming->endChannel($session->channel_id);

        $this->analytics->record((string) $session->id, LiveAnalyticsEventType::LiveEnded, $seller);

        event(new LiveSessionEnded($session));

        return $this->sessions->findWithRelations((string) $session->id) ?? $session;
    }

    public function listLive(int $limit = 20): Collection
    {
        return $this->sessions->listLive($limit);
    }

    public function listLiveCandidates(int $limit = 100): Collection
    {
        return $this->sessions->listLive($limit);
    }

    public function get(string $sessionId): LiveSession
    {
        $session = $this->sessions->findWithRelations($sessionId);
        if ($session === null) {
            throw new ResourceNotFoundException('Live session not found.');
        }

        return $session;
    }

    public function pinProduct(User $seller, string $sessionId, string $productId): LiveSession
    {
        $session = $this->requireSellerSession($seller, $sessionId);
        $this->assertLive($session);

        $product = $this->products->findByIdOrFail($productId);
        if ((string) $product->store_id !== (string) $session->store_id) {
            throw new ForbiddenException('Product does not belong to seller store.');
        }

        $pinnedCount = LiveSessionProduct::query()
            ->where('live_session_id', $session->id)
            ->where('is_pinned', true)
            ->count();

        $row = LiveSessionProduct::query()
            ->where('live_session_id', $session->id)
            ->where('product_id', $productId)
            ->first();

        if ($row?->is_pinned) {
            return $this->get((string) $session->id);
        }

        if ($pinnedCount >= self::MAX_PINNED) {
            throw new ConflictException('Maximum of 3 pinned products allowed.');
        }

        $offset = max(0, (int) now()->diffInSeconds($session->started_at ?? now()));

        if ($row === null) {
            $row = LiveSessionProduct::query()->create([
                'live_session_id' => $session->id,
                'product_id' => $productId,
                'is_pinned' => true,
                'pinned_at' => now(),
                'offset_seconds' => $offset,
                'sort_order' => $pinnedCount,
            ]);
        } else {
            $row->forceFill([
                'is_pinned' => true,
                'pinned_at' => now(),
                'offset_seconds' => $offset,
                'sort_order' => $pinnedCount,
            ])->save();
        }

        LiveChatMessage::query()->create([
            'live_session_id' => $session->id,
            'user_id' => $seller->id,
            'type' => LiveChatMessageType::System,
            'message' => 'Seller pinned '.$product->title,
            'metadata' => ['product_id' => $productId],
            'created_at' => now(),
        ]);

        $this->analytics->record(
            (string) $session->id,
            LiveAnalyticsEventType::ProductPinned,
            $seller,
            ['product_id' => $productId, 'offset_seconds' => $offset],
        );

        return $this->get((string) $session->id);
    }

    public function unpinProduct(User $seller, string $sessionId, string $productId): LiveSession
    {
        $session = $this->requireSellerSession($seller, $sessionId);
        $this->assertLive($session);

        $row = LiveSessionProduct::query()
            ->where('live_session_id', $session->id)
            ->where('product_id', $productId)
            ->where('is_pinned', true)
            ->first();

        if ($row === null) {
            throw new ResourceNotFoundException('Pinned product not found.');
        }

        $row->forceFill([
            'is_pinned' => false,
            'pinned_at' => null,
        ])->save();

        $this->analytics->record(
            (string) $session->id,
            LiveAnalyticsEventType::ProductUnpinned,
            $seller,
            ['product_id' => $productId],
        );

        return $this->get((string) $session->id);
    }

    public function sendChat(User $user, string $sessionId, string $message): LiveChatMessage
    {
        $session = $this->get($sessionId);
        $this->assertLive($session);

        return LiveChatMessage::query()->create([
            'live_session_id' => $session->id,
            'user_id' => $user->id,
            'type' => LiveChatMessageType::User,
            'message' => trim($message),
            'metadata' => null,
            'created_at' => now(),
        ]);
    }

    public function listChat(string $sessionId, ?int $afterId = null, int $limit = 50): Collection
    {
        $this->get($sessionId);

        $query = LiveChatMessage::query()
            ->with('user.profile')
            ->where('live_session_id', $sessionId)
            ->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query->limit($limit)->get();
    }

    public function join(User $user, string $sessionId): LiveViewerMetric
    {
        $session = $this->get($sessionId);
        $this->assertLive($session);

        $metrics = $this->viewerMetrics->join($sessionId, $user);
        $this->analytics->record($sessionId, LiveAnalyticsEventType::LiveJoined, $user);

        return $metrics;
    }

    public function leave(User $user, string $sessionId): LiveViewerMetric
    {
        $this->get($sessionId);
        $metrics = $this->viewerMetrics->leave($sessionId, $user);
        $this->analytics->record($sessionId, LiveAnalyticsEventType::LiveLeft, $user);

        return $metrics;
    }

    public function publisherToken(LiveSession $session, User $user): ?string
    {
        if ((string) $session->seller_id !== (string) $user->id) {
            return null;
        }

        return $this->streaming->generatePublisherToken($session->channel_id, (string) $user->id);
    }

    public function subscriberToken(LiveSession $session, User $user): string
    {
        return $this->streaming->generateSubscriberToken($session->channel_id, (string) $user->id);
    }

    private function requireSellerSession(User $seller, string $sessionId): LiveSession
    {
        $session = $this->get($sessionId);
        if ((string) $session->seller_id !== (string) $seller->id) {
            throw new ForbiddenException('Not the host of this live session.');
        }

        return $session;
    }

    private function assertLive(LiveSession $session): void
    {
        if ($session->status !== LiveSessionStatus::Live) {
            throw new ConflictException('Live session is not active.');
        }
    }
}
