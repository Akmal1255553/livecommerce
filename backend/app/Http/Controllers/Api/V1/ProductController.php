<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Enums\UserRole;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\CreateSellerProductRequest;
use App\Http\Requests\Product\UpdateSellerProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Store;
use App\Services\Product\ProductService;
use App\Services\Product\SellerProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly SellerProductService $sellerProducts,
        private readonly StoreRepositoryInterface $stores,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        if ($request->boolean('mine')) {
            $store = $this->resolveSellerStore($request);

            $result = $this->sellerProducts->list($store, $page, $perPage);

            return ApiResponse::paginated(
                ProductResource::collection($result['paginator']->items()),
                $result['pagination'],
            );
        }

        $result = $this->products->list(
            $page,
            $perPage,
            $request->query('sort_by'),
            (string) $request->query('sort_order', 'desc'),
        );

        return ApiResponse::paginated(
            ProductResource::collection($result['paginator']->items()),
            $result['pagination'],
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole(UserRole::Seller)) {
            $store = $this->stores->findActiveByUser($user);

            if ($store !== null) {
                try {
                    return ApiResponse::success(
                        new ProductResource($this->sellerProducts->show($store, $id)),
                    );
                } catch (ResourceNotFoundException) {
                    // Not this seller's product — show public catalog item if active.
                }
            }
        }

        return ApiResponse::success(
            new ProductResource($this->products->show($id)),
        );
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort_by' => ['nullable', 'string', 'in:price,title,rating,created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        $result = $this->products->search(
            (string) $request->query('q'),
            $page,
            $perPage,
            $request->filled('category_id') ? (int) $request->query('category_id') : null,
            $request->filled('min_price') ? (float) $request->query('min_price') : null,
            $request->filled('max_price') ? (float) $request->query('max_price') : null,
            $request->query('sort_by'),
            (string) $request->query('sort_order', 'desc'),
        );

        return ApiResponse::paginated(
            ProductResource::collection($result['paginator']->items()),
            $result['pagination'],
        );
    }

    public function store(CreateSellerProductRequest $request): JsonResponse
    {
        $product = $this->sellerProducts->create(
            $this->currentStore($request),
            $request->validated(),
        );

        return ApiResponse::created(new ProductResource($product));
    }

    public function update(UpdateSellerProductRequest $request, string $id): JsonResponse
    {
        $product = $this->sellerProducts->update(
            $this->currentStore($request),
            $id,
            $request->validated(),
        );

        return ApiResponse::success(new ProductResource($product));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->sellerProducts->delete($this->currentStore($request), $id);

        return ApiResponse::noContent();
    }

    private function currentStore(Request $request): Store
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        return $store;
    }

    private function resolveSellerStore(Request $request): Store
    {
        $user = $request->user();

        if ($user === null) {
            throw new UnauthorizedException;
        }

        if (! $user->hasRole(UserRole::Seller)) {
            throw new ForbiddenException('Seller access required.');
        }

        $store = $this->stores->findActiveByUser($user);

        if ($store === null) {
            throw new ForbiddenException('Active store required.');
        }

        return $store;
    }
}
