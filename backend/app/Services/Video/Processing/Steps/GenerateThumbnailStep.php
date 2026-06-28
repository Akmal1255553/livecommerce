<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\Enums\MediaAssetType;
use App\Enums\VideoProcessingStepName;
use App\Models\Video;
use App\Services\Media\MediaAssetService;
use App\Services\Video\Processing\AbstractProcessingStep;

class GenerateThumbnailStep extends AbstractProcessingStep
{
    public function __construct(
        VideoStateMachineInterface $stateMachine,
        private readonly StorageServiceInterface $storage,
        private readonly MediaServiceInterface $media,
        private readonly FfmpegTranscoderInterface $transcoder,
        private readonly MediaAssetService $assets,
    ) {
        parent::__construct($stateMachine);
    }

    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Thumbnail;
    }

    protected function execute(Video $video): void
    {
        $upload = $this->media->findUploadForVideo($video->id);

        if ($upload === null) {
            return;
        }

        $rawTemp = $this->storage->downloadToTemp($upload->storage_path);
        $thumbTemp = tempnam(sys_get_temp_dir(), 'lc_thumb_');

        if ($thumbTemp === false) {
            @unlink($rawTemp);

            throw new \RuntimeException('Unable to create thumbnail temp file.');
        }

        $thumbPath = $thumbTemp.'.jpg';
        rename($thumbTemp, $thumbPath);

        try {
            $this->transcoder->extractThumbnail($rawTemp, $thumbPath, 1);

            $storagePath = $this->media->videoThumbnailPath($video->id);
            $this->storage->putFile($storagePath, $thumbPath);

            $this->assets->register(
                $video,
                MediaAssetType::Thumbnail,
                $storagePath,
                'image/jpeg',
                [
                    'byte_size' => filesize($thumbPath) ?: null,
                    'width' => min($video->width ?? 720, 720),
                    'height' => $video->height,
                ],
            );
        } finally {
            @unlink($rawTemp);
            @unlink($thumbPath);
        }
    }
}
