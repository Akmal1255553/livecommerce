<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\StorageServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\PresignedMediaUrlRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly StorageServiceInterface $storage,
    ) {}

    public function presignedUrl(PresignedMediaUrlRequest $request): JsonResponse
    {
        $data = $request->validated();
        $presigned = $this->mediaService->createGenericPresignedUpload(
            $request->user(),
            $data['purpose'],
            $data['file_name'],
            $data['mime_type'],
            $data['file_size'],
        );

        return ApiResponse::success([
            'upload_url' => $presigned->url,
            'upload_method' => $presigned->method,
            'upload_headers' => $presigned->headers,
            'expires_at' => $presigned->expiresAt,
            'storage_path' => $presigned->storagePath,
            'public_url' => $this->storage->publicUrl($presigned->storagePath),
        ]);
    }
}
