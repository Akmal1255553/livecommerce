<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\DTOs\Pagination\CursorPaginationData;
use App\DTOs\Pagination\PaginationData;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(
        mixed $data = null,
        int $status = 200,
        ?array $meta = null,
        ?string $message = null,
    ): JsonResponse {
        return self::make(
            success: true,
            data: $data,
            status: $status,
            message: $message,
            meta: $meta,
        );
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, 201, message: $message);
    }

    public static function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, 202, message: $message);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function paginated(
        mixed $data,
        PaginationData $pagination,
        int $status = 200,
    ): JsonResponse {
        return self::success($data, $status, meta: $pagination->toArray());
    }

    public static function cursorPaginated(
        mixed $data,
        CursorPaginationData $pagination,
        int $status = 200,
    ): JsonResponse {
        return self::success($data, $status, meta: $pagination->meta());
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
    ): JsonResponse {
        return self::make(
            success: false,
            data: null,
            status: $status,
            message: $message,
            errors: $errors,
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, list<string>>|null  $errors
     */
    public static function make(
        bool $success,
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
        ?array $meta = null,
        ?array $errors = null,
    ): JsonResponse {
        $payload = ['success' => $success];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
