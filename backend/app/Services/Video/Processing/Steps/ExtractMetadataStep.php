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

class ExtractMetadataStep extends AbstractProcessingStep
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
        return VideoProcessingStepName::Metadata;
    }

    protected function execute(Video $video): void
    {
        $upload = $this->media->findUploadForVideo($video->id);

        if ($upload === null) {
            return;
        }

        $tempPath = $this->storage->downloadToTemp($upload->storage_path);

        try {
            $probe = $this->transcoder->probe($tempPath);

            $video->update([
                'duration' => $probe->duration,
                'width' => $probe->width,
                'height' => $probe->height,
                'codec' => $probe->codec,
                'bitrate' => $probe->bitrateKbps,
            ]);

            $this->assets->register(
                $video,
                MediaAssetType::Raw,
                $upload->storage_path,
                $upload->mime_type,
                [
                    'byte_size' => $upload->file_size,
                    'width' => $probe->width,
                    'height' => $probe->height,
                ],
            );
        } finally {
            @unlink($tempPath);
        }
    }
}
