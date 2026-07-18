<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\MessagingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\CreateConversationRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private readonly MessagingServiceInterface $messaging,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $page = $this->messaging->listConversations(
            $request->user(),
            $request->query('cursor'),
            $limit,
        );

        return ApiResponse::cursorPaginated(
            ConversationResource::collection($page->items),
            $page,
        );
    }

    public function store(CreateConversationRequest $request): JsonResponse
    {
        $conversation = $this->messaging->createConversation(
            actor: $request->user(),
            sellerId: $request->string('seller_id')->toString(),
            orderId: $request->input('order_id'),
            initialMessage: $request->input('message'),
        );

        return ApiResponse::created(new ConversationResource($conversation));
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 30)));
        $page = $this->messaging->listMessages(
            $request->user(),
            $id,
            $request->query('cursor'),
            $limit,
        );

        return ApiResponse::cursorPaginated(
            MessageResource::collection($page->items),
            $page,
        );
    }

    public function sendMessage(SendMessageRequest $request, string $id): JsonResponse
    {
        $message = $this->messaging->sendMessage(
            user: $request->user(),
            conversationId: $id,
            body: $request->input('body'),
            imageUrl: $request->input('image_url'),
        );

        return ApiResponse::created(new MessageResource($message));
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $conversation = $this->messaging->markRead($request->user(), $id);

        return ApiResponse::success(new ConversationResource($conversation));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->messaging->unreadCount($request->user()),
        ]);
    }
}
