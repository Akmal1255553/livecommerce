<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\StoreServiceInterface;
use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreServiceInterface $stores,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $store = $this->stores->getBySlug($slug);

        return ApiResponse::success(new StoreResource($store));
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $store = $this->stores->getBySlug($slug);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);
        $paginator = $this->stores->listPublicProducts($store, $page, $perPage);

        return ApiResponse::paginated(
            ProductResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }
}
