<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Enums\MediaUploadStatus;
use App\Models\MediaUpload;

class MediaUploadRepository extends BaseEloquentRepository implements MediaUploadRepositoryInterface
{
    public function __construct(MediaUpload $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): MediaUpload
    {
        /** @var MediaUpload $upload */
        $upload = $this->model->newQuery()->create($attributes);

        return $upload;
    }

    public function updateStatus(MediaUpload $upload, string $status, ?string $checksum = null): MediaUpload
    {
        $upload->status = MediaUploadStatus::from($status);
        if ($checksum !== null) {
            $upload->checksum = $checksum;
        }
        $upload->save();

        return $upload;
    }

    public function findForVideo(string $videoId): ?MediaUpload
    {
        return $this->model->newQuery()
            ->where('entity_type', 'video')
            ->where('entity_id', $videoId)
            ->latest('created_at')
            ->first();
    }
}
