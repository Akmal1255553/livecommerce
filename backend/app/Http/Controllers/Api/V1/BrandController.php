<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Responses\ApiResponse;
use App\Services\Product\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brands,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        $result = $this->brands->list($page, $perPage);

        return ApiResponse::paginated(
            BrandResource::collection($result['paginator']->items()),
            $result['pagination'],
        );
    }
}
