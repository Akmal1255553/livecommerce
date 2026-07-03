<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\OrderServiceInterface;
use App\DTOs\Pagination\PaginationData;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ResolveRefundRequest;
use App\Http\Requests\Order\UpdateSellerOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerOrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);
        $status = $request->query('status');
        $filters = [];

        if (is_string($status) && $status !== '') {
            $filters['status'] = OrderStatus::from($status);
        }

        $paginator = $this->orders->listForSeller((string) $store->id, $filters, $page, $perPage);

        return ApiResponse::paginated(
            OrderResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $order = $this->orders->getForSeller((string) $store->id, $id);

        return ApiResponse::success(new OrderResource($order));
    }

    public function updateStatus(UpdateSellerOrderStatusRequest $request, string $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $version = $request->header('If-Match');

        $order = $this->orders->updateStatusForSeller(
            (string) $store->id,
            $id,
            OrderStatus::from($request->validated('status')),
            $request->validated('tracking_number'),
            $request->validated('carrier'),
            $version !== null ? (int) $version : null,
            $request->header('Idempotency-Key'),
        );

        return ApiResponse::success(new OrderResource($order));
    }

    public function resolveRefund(ResolveRefundRequest $request, string $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $approve = $request->validated('action') === 'approve';

        $order = $this->orders->resolveRefund(
            (string) $store->id,
            $id,
            $approve,
            $request->header('Idempotency-Key'),
        );

        return ApiResponse::success(new OrderResource($order));
    }
}
