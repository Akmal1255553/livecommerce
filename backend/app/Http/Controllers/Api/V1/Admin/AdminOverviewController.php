<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;

class AdminOverviewController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->admin->overview());
    }
}
