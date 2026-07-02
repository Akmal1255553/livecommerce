<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\Product\CategoryService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly ProductService $products,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CategoryResource::collection($this->categories->tree()),
        );
    }

    public function products(Request $request, int $id): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        $result = $this->products->listByCategory($id, $page, $perPage);

        return ApiResponse::paginated(
            ProductResource::collection($result['paginator']->items()),
            $result['pagination'],
        );
    }
}
