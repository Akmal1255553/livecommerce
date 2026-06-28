<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\DTOs\Video\HlsProfile;
use App\Enums\MediaAssetType;
use App\Enums\VideoProcessingStepName;

class TranscodeHls720Step extends AbstractTranscodeHlsStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::TranscodeHls720;
    }

    protected function rendition(): string
    {
        return '720p';
    }

    protected function assetType(): MediaAssetType
    {
        return MediaAssetType::Hls720p;
    }

    protected function profile(): HlsProfile
    {
        $config = config('video.hls_profiles.720p');

        return new HlsProfile(
            name: (string) $config['name'],
            height: (int) $config['height'],
            videoBitrateKbps: (int) $config['video_bitrate_kbps'],
            audioBitrateKbps: (int) $config['audio_bitrate_kbps'],
        );
    }
}
