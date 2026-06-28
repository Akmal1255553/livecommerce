<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Enums\VideoProcessingStepName;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;
use Illuminate\Support\Facades\Log;

class VirusScanStep extends AbstractProcessingStep
{
    public function __construct(
        VideoStateMachineInterface $stateMachine,
        private readonly StorageServiceInterface $storage,
        private readonly MediaServiceInterface $media,
    ) {
        parent::__construct($stateMachine);
    }

    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::VirusScan;
    }

    protected function execute(Video $video): void
    {
        $upload = $this->media->findUploadForVideo($video->id);

        if ($upload === null) {
            return;
        }

        $hash = hash('sha256', $this->storage->get($upload->storage_path));

        Log::info('Virus scan stub passed.', [
            'video_id' => $video->id,
            'sha256' => $hash,
        ]);
    }
}
