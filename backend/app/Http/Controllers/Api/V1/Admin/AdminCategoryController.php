<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Services\Product\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image_url' => ['nullable', 'string', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] ??= Str::slug($data['name']);
        $data['is_active'] = true;

        $category = $this->categories->create($data);

        return ApiResponse::created(new CategoryResource($category));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var Category|null $category */
        $category = Category::query()->find($id);
        if ($category === null) {
            return ApiResponse::error('Category not found.', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:120', 'unique:categories,slug,'.$category->id],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image_url' => ['nullable', 'string', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = $this->categories->update($category, $data);

        return ApiResponse::success(new CategoryResource($category));
    }

    public function destroy(string $id): JsonResponse
    {
        /** @var Category|null $category */
        $category = Category::query()->find($id);
        if ($category === null) {
            return ApiResponse::error('Category not found.', 404);
        }

        $this->categories->deactivate($category);

        return ApiResponse::noContent();
    }
}
