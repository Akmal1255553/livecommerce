<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\DTOs\Video\HlsProfile;
use App\Enums\MediaAssetType;
use App\Enums\VideoProcessingStepName;

class TranscodeHls480Step extends AbstractTranscodeHlsStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::TranscodeHls480;
    }

    protected function rendition(): string
    {
        return '480p';
    }

    protected function assetType(): MediaAssetType
    {
        return MediaAssetType::Hls480p;
    }

    protected function profile(): HlsProfile
    {
        $config = config('video.hls_profiles.480p');

        return new HlsProfile(
            name: (string) $config['name'],
            height: (int) $config['height'],
            videoBitrateKbps: (int) $config['video_bitrate_kbps'],
            audioBitrateKbps: (int) $config['audio_bitrate_kbps'],
        );
    }
}
