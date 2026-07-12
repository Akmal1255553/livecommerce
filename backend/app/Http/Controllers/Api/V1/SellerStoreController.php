<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\StoreServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerApplyRequest;
use App\Http\Resources\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerStoreController extends Controller
{
    public function __construct(
        private readonly StoreServiceInterface $stores,
    ) {}

    public function apply(SellerApplyRequest $request): JsonResponse
    {
        $store = $this->stores->apply($request->user(), $request->validated());

        return ApiResponse::created(new StoreResource($store));
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $dashboard = $this->stores->dashboard($store);

        return ApiResponse::success($dashboard->toArray());
    }

    public function analyticsSummary(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $period = (string) $request->query('period', 'last_30_days');
        $summary = $this->stores->analyticsSummary($store, $period);

        return ApiResponse::success($summary->toArray());
    }
}
