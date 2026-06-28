<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Video\ConfirmVideoUploadRequest;
use App\Http\Requests\Video\CreateVideoUploadRequest;
use App\Http\Requests\Video\UpdateVideoMetadataRequest;
use App\Http\Resources\VideoResource;
use App\Http\Responses\ApiResponse;
use App\Services\Video\VideoManagementService;
use App\Services\Video\VideoService;
use App\Services\Video\VideoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoUploadService $uploadService,
        private readonly VideoManagementService $managementService,
        private readonly VideoService $videoService,
    ) {}

    public function store(CreateVideoUploadRequest $request): JsonResponse
    {
        $result = $this->uploadService->initiateUpload(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::created([
            'video' => new VideoResource($result['video']),
            'upload_url' => $result['upload']->url,
            'upload_method' => $result['upload']->method,
            'upload_headers' => $result['upload']->headers,
            'expires_at' => $result['upload']->expiresAt,
        ]);
    }

    public function confirmUpload(ConfirmVideoUploadRequest $request, string $id): JsonResponse
    {
        $video = $this->uploadService->confirmUpload(
            $request->user(),
            $id,
            $request->validated('checksum'),
        );

        return ApiResponse::accepted([
            'video' => new VideoResource($video),
            'message' => 'Upload confirmed. Processing started.',
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $video = $this->uploadService->getViewableVideo($id, $request->user());
        $enriched = $this->videoService
            ->enrichVideosForViewer(collect([$video]), $request->user())
            ->first();

        return ApiResponse::success(new VideoResource($enriched));
    }

    public function update(UpdateVideoMetadataRequest $request, string $id): JsonResponse
    {
        $video = $this->managementService->updateMetadata(
            $request->user(),
            $id,
            $request->validated(),
        );

        return ApiResponse::success(new VideoResource($video));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->managementService->softDelete($request->user(), $id);

        return ApiResponse::noContent();
    }
}
