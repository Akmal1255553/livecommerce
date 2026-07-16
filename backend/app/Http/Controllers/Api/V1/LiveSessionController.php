<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\LiveSessionServiceInterface;
use App\Contracts\Services\StreamingProviderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\AddLiveCartItemRequest;
use App\Http\Requests\Live\PinLiveProductRequest;
use App\Http\Requests\Live\SendLiveChatRequest;
use App\Http\Requests\Live\StartLiveSessionRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\LiveChatMessageResource;
use App\Http\Resources\LiveSessionResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSessionController extends Controller
{
    public function __construct(
        private readonly LiveSessionServiceInterface $liveSessions,
        private readonly StreamingProviderInterface $streaming,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $sessions = $this->liveSessions->listLive($limit);

        return ApiResponse::success(LiveSessionResource::collection($sessions));
    }

    public function replays(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $sessions = $this->liveSessions->listReplays($limit);

        return ApiResponse::success(LiveSessionResource::collection($sessions));
    }

    public function start(StartLiveSessionRequest $request): JsonResponse
    {
        /** @var \App\Models\Store $store */
        $store = $request->attributes->get('store');
        $session = $this->liveSessions->start($request->user(), $store, $request->validated());
        $pub = $this->liveSessions->publisherToken($session, $request->user());
        $sub = $this->liveSessions->subscriberToken($session, $request->user());

        return ApiResponse::created(new LiveSessionResource($session, $pub, $sub));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = $this->liveSessions->get($id);
        $user = $request->user();

        $publisherToken = null;
        $subscriberToken = $this->streaming->generateSubscriberToken(
            $session->channel_id,
            $user?->id ?? 'guest',
        );

        if ($user !== null) {
            $publisherToken = $this->liveSessions->publisherToken($session, $user);
        }

        return ApiResponse::success(new LiveSessionResource($session, $publisherToken, $subscriberToken));
    }

    public function end(Request $request, string $id): JsonResponse
    {
        $session = $this->liveSessions->end($request->user(), $id);

        return ApiResponse::success(new LiveSessionResource($session));
    }

    public function pinProduct(PinLiveProductRequest $request, string $id): JsonResponse
    {
        $session = $this->liveSessions->pinProduct(
            $request->user(),
            $id,
            $request->validated('product_id'),
        );

        return ApiResponse::success(new LiveSessionResource($session));
    }

    public function unpinProduct(Request $request, string $id, string $productId): JsonResponse
    {
        $session = $this->liveSessions->unpinProduct($request->user(), $id, $productId);

        return ApiResponse::success(new LiveSessionResource($session));
    }

    public function chatIndex(Request $request, string $id): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 50)), 100);
        $afterId = $request->query('after_id');
        $messages = $this->liveSessions->listChat(
            $id,
            $afterId !== null && $afterId !== '' ? (int) $afterId : null,
            $limit,
        );

        return ApiResponse::success(LiveChatMessageResource::collection($messages));
    }

    public function chatStore(SendLiveChatRequest $request, string $id): JsonResponse
    {
        $message = $this->liveSessions->sendChat(
            $request->user(),
            $id,
            $request->validated('message'),
        );

        return ApiResponse::created(new LiveChatMessageResource($message->load('user.profile')));
    }

    public function join(Request $request, string $id): JsonResponse
    {
        $metrics = $this->liveSessions->join($request->user(), $id);

        return ApiResponse::success([
            'current_viewers' => $metrics->current_viewers,
            'peak_viewers' => $metrics->peak_viewers,
            'unique_viewers' => $metrics->unique_viewers,
        ]);
    }

    public function leave(Request $request, string $id): JsonResponse
    {
        $metrics = $this->liveSessions->leave($request->user(), $id);

        return ApiResponse::success([
            'current_viewers' => $metrics->current_viewers,
            'peak_viewers' => $metrics->peak_viewers,
            'unique_viewers' => $metrics->unique_viewers,
        ]);
    }

    public function addToCart(AddLiveCartItemRequest $request, string $id): JsonResponse
    {
        $result = $this->liveSessions->addToCart(
            $request->user(),
            $id,
            $request->validated('product_id'),
            (int) $request->validated('quantity', 1),
        );

        return ApiResponse::success([
            'cart' => (new CartResource($result->cart))->resolve($request),
            'chat_message' => (new LiveChatMessageResource($result->message))->resolve($request),
        ]);
    }
}
