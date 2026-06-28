<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\Enums\MediaAssetType;
use App\Enums\MediaUploadStatus;
use App\Enums\VideoProcessingStepName;
use App\Events\VideoPublished;
use App\Models\Video;
use App\Services\Media\MediaAssetService;
use App\Services\Video\Processing\AbstractProcessingStep;

class PublishVideoStep extends AbstractProcessingStep
{
    public function __construct(
        VideoStateMachineInterface $stateMachine,
        private readonly VideoRepositoryInterface $videos,
        private readonly MediaUploadRepositoryInterface $uploads,
        private readonly MediaServiceInterface $media,
        private readonly StorageServiceInterface $storage,
        private readonly FfmpegTranscoderInterface $transcoder,
        private readonly MediaAssetService $assets,
    ) {
        parent::__construct($stateMachine);
    }

    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Publish;
    }

    protected function execute(Video $video): void
    {
        $masterTemp = tempnam(sys_get_temp_dir(), 'lc_master_');

        if ($masterTemp === false) {
            throw new \RuntimeException('Unable to create master playlist temp file.');
        }

        $masterPath = $masterTemp.'.m3u8';
        rename($masterTemp, $masterPath);

        try {
            $variants = [
                2_500_000 => $this->media->videoHlsRenditionPlaylistPath($video->id, '720p'),
                1_000_000 => $this->media->videoHlsRenditionPlaylistPath($video->id, '480p'),
            ];

            $this->transcoder->buildMasterPlaylist($variants, $masterPath);

            $storagePath = $this->media->videoHlsMasterPath($video->id);
            $this->storage->putFile($storagePath, $masterPath);

            $this->assets->register(
                $video,
                MediaAssetType::HlsMaster,
                $storagePath,
                'application/vnd.apple.mpegurl',
            );

            $videoUrl = $this->assets->resolvePlaybackUrl($video);
            $thumbnailUrl = $this->assets->resolveThumbnailUrl($video);

            $this->videos->update($video, [
                'video_url' => $videoUrl,
                'thumbnail_url' => $thumbnailUrl,
            ]);

            $upload = $this->uploads->findForVideo($video->id);

            if ($upload !== null) {
                $upload->processed_at = now();
                $this->uploads->updateStatus($upload, MediaUploadStatus::Completed->value);
            }

            $published = $this->stateMachine->markPublished($video->fresh() ?? $video);

            event(new VideoPublished($published->id, $published->user_id));
        } finally {
            @unlink($masterPath);
        }
    }
}
