<?php

declare(strict_types=1);

namespace App\Services\LiveSession;

use App\Contracts\Services\LiveAnalyticsServiceInterface;
use App\DTOs\Live\LiveSessionAnalyticsData;
use App\DTOs\Live\SellerLiveAnalyticsOverviewData;
use App\Enums\LiveAnalyticsEventType;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\LiveAnalyticsEvent;
use App\Models\LiveChatMessage;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class LiveAnalyticsService extends BaseService implements LiveAnalyticsServiceInterface
{
    public function __construct(StructuredLogger $logger)
    {
        parent::__construct($logger);
    }

    public function record(
        string $liveSessionId,
        LiveAnalyticsEventType $type,
        ?User $user = null,
        ?array $payload = null,
    ): LiveAnalyticsEvent {
        $event = LiveAnalyticsEvent::query()->create([
            'live_session_id' => $liveSessionId,
            'user_id' => $user?->id,
            'event_type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        $this->logger->info('live.analytics', [
            'live_session_id' => $liveSessionId,
            'event_type' => $type->value,
            'user_id' => $user?->id,
        ]);

        return $event;
    }

    public function summarizeSession(string $sessionId): LiveSessionAnalyticsData
    {
        $session = LiveSession::query()
            ->with('viewerMetrics')
            ->find($sessionId);

        if ($session === null) {
            throw new ResourceNotFoundException('Live session not found.');
        }

        return $this->buildSessionSummary($session);
    }

    public function overviewForSeller(User $seller, int $limit = 20): SellerLiveAnalyticsOverviewData
    {
        $sessions = LiveSession::query()
            ->with('viewerMetrics')
            ->where('seller_id', $seller->id)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();

        $summaries = $sessions->map(fn (LiveSession $session) => $this->buildSessionSummary($session));

        $totalUnique = (int) $summaries->sum(fn (LiveSessionAnalyticsData $s) => $s->uniqueViewers);
        $totalPeak = (int) $summaries->sum(fn (LiveSessionAnalyticsData $s) => $s->peakViewers);
        $totalCart = (int) $summaries->sum(fn (LiveSessionAnalyticsData $s) => $s->productsAddedToCart);
        $totalPins = (int) $summaries->sum(fn (LiveSessionAnalyticsData $s) => $s->productsPinned);
        $count = $summaries->count();
        $avgConversion = $count === 0
            ? 0.0
            : round($summaries->avg(fn (LiveSessionAnalyticsData $s) => $s->cartConversionRate), 4);

        return new SellerLiveAnalyticsOverviewData(
            totalSessions: $count,
            totalUniqueViewers: $totalUnique,
            totalPeakViewers: $totalPeak,
            totalAddToCart: $totalCart,
            totalPins: $totalPins,
            avgCartConversionRate: (float) $avgConversion,
            sessions: $summaries->map(fn (LiveSessionAnalyticsData $s) => $s->toArray())->values()->all(),
        );
    }

    public function assertSellerOwnsSession(User $seller, string $sessionId): LiveSession
    {
        $session = LiveSession::query()->find($sessionId);
        if ($session === null) {
            throw new ResourceNotFoundException('Live session not found.');
        }

        if ((string) $session->seller_id !== (string) $seller->id) {
            throw new ForbiddenException('Not the host of this live session.');
        }

        return $session;
    }

    private function buildSessionSummary(LiveSession $session): LiveSessionAnalyticsData
    {
        $metrics = $session->viewerMetrics;
        $rawCounts = LiveAnalyticsEvent::query()
            ->where('live_session_id', $session->id)
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->get();

        $normalizedCounts = [];
        foreach (LiveAnalyticsEventType::cases() as $type) {
            $normalizedCounts[$type->value] = 0;
        }
        foreach ($rawCounts as $row) {
            $key = $row->event_type instanceof LiveAnalyticsEventType
                ? $row->event_type->value
                : (string) $row->event_type;
            $normalizedCounts[$key] = (int) $row->total;
        }

        $pins = (int) ($normalizedCounts[LiveAnalyticsEventType::ProductPinned->value] ?? 0);
        $addToCart = (int) ($normalizedCounts[LiveAnalyticsEventType::ProductAddedToCart->value] ?? 0);
        $unique = (int) ($metrics?->unique_viewers ?? 0);
        $conversion = $unique > 0 ? round($addToCart / $unique, 4) : 0.0;

        $durationSeconds = null;
        if ($session->started_at !== null && $session->ended_at !== null) {
            $durationSeconds = max(0, (int) $session->started_at->diffInSeconds($session->ended_at));
        }

        $chatMessages = (int) LiveChatMessage::query()
            ->where('live_session_id', $session->id)
            ->count();

        $topProducts = $this->topProductsForSession((string) $session->id);

        return new LiveSessionAnalyticsData(
            sessionId: (string) $session->id,
            title: $session->title,
            status: $session->status->value,
            startedAt: $session->started_at?->toIso8601String(),
            endedAt: $session->ended_at?->toIso8601String(),
            durationSeconds: $durationSeconds,
            peakViewers: (int) ($metrics?->peak_viewers ?? 0),
            uniqueViewers: $unique,
            currentViewers: (int) ($metrics?->current_viewers ?? 0),
            chatMessages: $chatMessages,
            productsPinned: $pins,
            productsAddedToCart: $addToCart,
            cartConversionRate: $conversion,
            eventCounts: $normalizedCounts,
            topProducts: $topProducts,
            replayUrl: $session->replay_url,
        );
    }

    /**
     * @return list<array{product_id: string, title: string|null, pins: int, add_to_cart: int}>
     */
    private function topProductsForSession(string $sessionId): array
    {
        $rows = LiveAnalyticsEvent::query()
            ->where('live_session_id', $sessionId)
            ->whereIn('event_type', [
                LiveAnalyticsEventType::ProductPinned,
                LiveAnalyticsEventType::ProductAddedToCart,
            ])
            ->get(['event_type', 'payload']);

        $agg = [];
        foreach ($rows as $row) {
            $productId = $row->payload['product_id'] ?? null;
            if (! is_string($productId) || $productId === '') {
                continue;
            }
            if (! isset($agg[$productId])) {
                $agg[$productId] = ['pins' => 0, 'add_to_cart' => 0];
            }
            if ($row->event_type === LiveAnalyticsEventType::ProductPinned) {
                $agg[$productId]['pins']++;
            }
            if ($row->event_type === LiveAnalyticsEventType::ProductAddedToCart) {
                $agg[$productId]['add_to_cart']++;
            }
        }

        if ($agg === []) {
            return [];
        }

        $titles = Product::query()
            ->whereIn('id', array_keys($agg))
            ->pluck('title', 'id');

        $list = [];
        foreach ($agg as $productId => $stats) {
            $list[] = [
                'product_id' => $productId,
                'title' => $titles[$productId] ?? null,
                'pins' => $stats['pins'],
                'add_to_cart' => $stats['add_to_cart'],
            ];
        }

        usort($list, static fn (array $a, array $b): int => ($b['add_to_cart'] + $b['pins']) <=> ($a['add_to_cart'] + $a['pins']));

        return array_values(array_slice($list, 0, 5));
    }
}
