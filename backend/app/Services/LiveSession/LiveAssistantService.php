<?php

declare(strict_types=1);

namespace App\Services\LiveSession;

use App\Contracts\Repositories\LiveSessionRepositoryInterface;
use App\Contracts\Services\LiveAssistantServiceInterface;
use App\DTOs\Live\LiveAssistantSuggestionData;
use App\Enums\LiveChatMessageType;
use App\Enums\LiveSessionStatus;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Enums\ProductStatus;
use App\Models\LiveChatMessage;
use App\Models\LiveSession;
use App\Models\LiveSessionProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Collection;

class LiveAssistantService extends BaseService implements LiveAssistantServiceInterface
{
    public function __construct(
        StructuredLogger $logger,
        private readonly LiveSessionRepositoryInterface $sessions,
    ) {
        parent::__construct($logger);
    }

    public function suggestionsForHost(User $seller, string $sessionId): Collection
    {
        $session = $this->sessions->findWithRelations($sessionId);
        if ($session === null) {
            throw new ResourceNotFoundException('Live session not found.');
        }

        if ((string) $session->seller_id !== (string) $seller->id) {
            throw new ForbiddenException('Not the host of this live session.');
        }

        if ($session->status !== LiveSessionStatus::Live) {
            throw new ConflictException('Live session is not active.');
        }

        $suggestions = collect();

        $suggestions = $suggestions
            ->merge($this->suggestPins($session))
            ->merge($this->suggestFaqReplies($session))
            ->merge($this->suggestEngagement($session))
            ->merge($this->suggestCartMomentum($session));

        return $suggestions
            ->sortBy(fn (LiveAssistantSuggestionData $s) => $s->priority)
            ->values()
            ->take(8);
    }

    /**
     * @return Collection<int, LiveAssistantSuggestionData>
     */
    private function suggestPins(LiveSession $session): Collection
    {
        $pinnedCount = LiveSessionProduct::query()
            ->where('live_session_id', $session->id)
            ->where('is_pinned', true)
            ->count();

        $candidates = LiveSessionProduct::query()
            ->with('product')
            ->where('live_session_id', $session->id)
            ->where('is_pinned', false)
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        if ($candidates->isEmpty()) {
            $storeProducts = Product::query()
                ->where('store_id', $session->store_id)
                ->where('status', ProductStatus::Active)
                ->orderByDesc('updated_at')
                ->limit(2)
                ->get();

            return $storeProducts->map(function (Product $product, int $index) use ($pinnedCount): LiveAssistantSuggestionData {
                return new LiveAssistantSuggestionData(
                    id: 'pin-store-'.$product->id,
                    type: 'pin_product',
                    priority: $pinnedCount === 0 ? 1 : 3 + $index,
                    title: $pinnedCount === 0 ? 'Pin your first product' : 'Pin a store product',
                    body: 'Suggest pinning «'.$product->title.'» so viewers can buy without leaving the stream.',
                    action: 'pin_product',
                    payload: ['product_id' => (string) $product->id],
                );
            });
        }

        return $candidates->map(function (LiveSessionProduct $row, int $index) use ($pinnedCount): LiveAssistantSuggestionData {
            $title = $row->product?->title ?? 'product';

            return new LiveAssistantSuggestionData(
                id: 'pin-'.$row->product_id,
                type: 'pin_product',
                priority: $pinnedCount === 0 ? 1 : 3 + $index,
                title: $pinnedCount === 0 ? 'Pin a product now' : 'Pin another product',
                body: '«'.$title.'» is attached to this live but not pinned yet.',
                action: 'pin_product',
                payload: ['product_id' => (string) $row->product_id],
            );
        });
    }

    /**
     * @return Collection<int, LiveAssistantSuggestionData>
     */
    private function suggestFaqReplies(LiveSession $session): Collection
    {
        $recent = LiveChatMessage::query()
            ->where('live_session_id', $session->id)
            ->where('type', LiveChatMessageType::User)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $patterns = [
            'shipping' => [
                'keys' => ['ship', 'delivery', 'достав', 'доставка', 'yetkaz'],
                'reply' => 'Shipping usually takes 1–3 days in Tashkent and 2–5 days across Uzbekistan.',
                'title' => 'Answer shipping question',
            ],
            'price' => [
                'keys' => ['price', 'cost', 'how much', 'narx', 'цена', 'сколько', 'qancha'],
                'reply' => 'Tap the pinned product to see the current live price and add it to cart.',
                'title' => 'Answer price question',
            ],
            'size' => [
                'keys' => ['size', 'размер', 'olcham', 'fit'],
                'reply' => 'Check the size chart on the product page — if unsure, message us after order and we will help exchange.',
                'title' => 'Answer size question',
            ],
        ];

        $out = collect();
        foreach ($recent as $message) {
            $text = mb_strtolower($message->message);
            foreach ($patterns as $key => $rule) {
                foreach ($rule['keys'] as $needle) {
                    if (str_contains($text, $needle)) {
                        $out->push(new LiveAssistantSuggestionData(
                            id: 'reply-'.$message->id.'-'.$key,
                            type: 'reply_faq',
                            priority: 2,
                            title: $rule['title'],
                            body: 'Viewer asked: «'.$message->message.'»',
                            action: 'copy_reply',
                            payload: [
                                'reply_text' => $rule['reply'],
                                'chat_message_id' => $message->id,
                            ],
                        ));
                        break 2;
                    }
                }
            }
        }

        return $out->unique(fn (LiveAssistantSuggestionData $s) => $s->type.$s->title)->values();
    }

    /**
     * @return Collection<int, LiveAssistantSuggestionData>
     */
    private function suggestEngagement(LiveSession $session): Collection
    {
        $viewers = (int) ($session->viewerMetrics?->current_viewers ?? $session->viewer_count ?? 0);
        $chatCount = LiveChatMessage::query()
            ->where('live_session_id', $session->id)
            ->where('type', LiveChatMessageType::User)
            ->count();

        $elapsed = max(0, (int) now()->diffInSeconds($session->started_at ?? now()));
        $out = collect();

        if ($elapsed >= 60 && $viewers <= 1) {
            $out->push(new LiveAssistantSuggestionData(
                id: 'engage-viewers',
                type: 'engage',
                priority: 4,
                title: 'Boost engagement',
                body: 'Viewer count is low. Ask a question in chat or flash a limited offer on a pinned product.',
                action: 'copy_reply',
                payload: [
                    'reply_text' => 'Anyone watching from Tashkent? Drop a 👋 and I will pin today’s deal!',
                ],
            ));
        }

        if ($elapsed >= 90 && $chatCount === 0) {
            $out->push(new LiveAssistantSuggestionData(
                id: 'engage-chat',
                type: 'engage',
                priority: 5,
                title: 'Start the conversation',
                body: 'No viewer messages yet. Prompt chat with a simple question.',
                action: 'copy_reply',
                payload: [
                    'reply_text' => 'Quick poll: which color should I show next — drop 1 or 2 in chat!',
                ],
            ));
        }

        return $out;
    }

    /**
     * @return Collection<int, LiveAssistantSuggestionData>
     */
    private function suggestCartMomentum(LiveSession $session): Collection
    {
        $cartAdds = LiveChatMessage::query()
            ->where('live_session_id', $session->id)
            ->where('type', LiveChatMessageType::Commerce)
            ->count();

        if ($cartAdds < 1) {
            return collect();
        }

        return collect([
            new LiveAssistantSuggestionData(
                id: 'momentum-cart',
                type: 'highlight',
                priority: 2,
                title: 'Cart momentum',
                body: 'Viewers are adding to cart. Thank them and re-pin the hottest product.',
                action: 'copy_reply',
                payload: [
                    'reply_text' => 'Thanks for the orders! Still a few left on the pinned item — grab it while it is live-priced.',
                ],
            ),
        ]);
    }
}
