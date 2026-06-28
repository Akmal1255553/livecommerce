<?php

declare(strict_types=1);

namespace App\Services\Video\Ffmpeg;

use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\DTOs\Video\HlsProfile;
use App\DTOs\Video\VideoProbeResult;

class FakeFfmpegTranscoder implements FfmpegTranscoderInterface
{
    public function probe(string $inputPath): VideoProbeResult
    {
        return new VideoProbeResult(
            duration: 3,
            width: 1080,
            height: 1920,
            codec: 'h264',
            bitrateKbps: 1500,
        );
    }

    public function extractThumbnail(string $inputPath, string $outputPath, int $atSecond = 1): void
    {
        $dir = dirname($outputPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, $this->minimalJpeg());
    }

    public function transcodeToHls(string $inputPath, string $outputDir, HlsProfile $profile): array
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $playlist = $outputDir.'/playlist.m3u8';
        $segment = $outputDir.'/segment_0001.ts';

        file_put_contents($playlist, "#EXTM3U\n#EXT-X-VERSION:3\n#EXTINF:2.0,\nsegment_0001.ts\n#EXT-X-ENDLIST\n");
        file_put_contents($segment, 'fake-ts-segment');

        return [$segment];
    }

    public function buildMasterPlaylist(array $variants, string $masterPath): void
    {
        $dir = dirname($masterPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];

        foreach ($variants as $bandwidth => $playlistPath) {
            $lines[] = '#EXT-X-STREAM-INF:BANDWIDTH='.$bandwidth;
            $lines[] = basename($playlistPath);
        }

        file_put_contents($masterPath, implode("\n", $lines)."\n");
    }

    private function minimalJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//2wBDAQoLCw4NDw4QEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
            true
        ) ?: '';
    }
}
