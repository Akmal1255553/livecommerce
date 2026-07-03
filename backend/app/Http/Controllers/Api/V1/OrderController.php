<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\OrderServiceInterface;
use App\DTOs\Pagination\PaginationData;
use App\Enums\OrderStatus;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\RequestRefundRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);
        $status = $request->query('status');
        $filters = [];

        if (is_string($status) && $status !== '') {
            $filters['status'] = OrderStatus::from($status);
        }

        $paginator = $this->orders->listForBuyer((string) $user->id, $filters, $page, $perPage);

        return ApiResponse::paginated(
            OrderResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->orders->getForBuyer((string) $request->user()->id, $id);

        return ApiResponse::success(new OrderResource($order));
    }

    public function cancel(CancelOrderRequest $request, string $id): JsonResponse
    {
        $order = $this->orders->cancelForBuyer(
            (string) $request->user()->id,
            $id,
            $request->header('Idempotency-Key'),
            $request->validated('reason'),
        );

        return ApiResponse::success(new OrderResource($order));
    }

    public function requestRefund(RequestRefundRequest $request, string $id): JsonResponse
    {
        $order = $this->orders->requestRefund(
            (string) $request->user()->id,
            $id,
            $request->validated('reason'),
            $request->header('Idempotency-Key'),
        );

        return ApiResponse::success(new OrderResource($order));
    }

    public function showRefund(Request $request, string $id): JsonResponse
    {
        $refund = RefundRequest::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($refund === null) {
            throw new ResourceNotFoundException('Refund request not found.');
        }

        return ApiResponse::success(new RefundResource($refund));
    }
}
