<?php

declare(strict_types=1);

namespace App\Services\Video\Ffmpeg;

use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\DTOs\Video\HlsProfile;
use App\DTOs\Video\VideoProbeResult;
use App\Exceptions\Domain\VideoProcessingException;
use Symfony\Component\Process\Process;

class CliFfmpegTranscoder implements FfmpegTranscoderInterface
{
    public function __construct(
        private readonly string $ffmpegPath,
        private readonly string $ffprobePath,
    ) {}

    public function probe(string $inputPath): VideoProbeResult
    {
        $process = new Process([
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $inputPath,
        ]);

        $process->mustRun();

        /** @var array{format?: array{duration?: string, bit_rate?: string}, streams?: list<array{codec_type?: string, codec_name?: string, width?: int, height?: int}>} $data */
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $videoStream = collect($data['streams'] ?? [])
            ->first(fn (array $stream): bool => ($stream['codec_type'] ?? '') === 'video');

        if (! is_array($videoStream)) {
            throw new VideoProcessingException('validation_failed', 'No video stream found in input file.');
        }

        $duration = (int) round((float) ($data['format']['duration'] ?? 0));
        $bitrate = isset($data['format']['bit_rate']) ? (int) round(((int) $data['format']['bit_rate']) / 1000) : null;

        return new VideoProbeResult(
            duration: $duration,
            width: (int) ($videoStream['width'] ?? 0),
            height: (int) ($videoStream['height'] ?? 0),
            codec: (string) ($videoStream['codec_name'] ?? 'unknown'),
            bitrateKbps: $bitrate,
        );
    }

    public function extractThumbnail(string $inputPath, string $outputPath, int $atSecond = 1): void
    {
        $process = new Process([
            $this->ffmpegPath,
            '-y',
            '-ss', (string) $atSecond,
            '-i', $inputPath,
            '-frames:v', '1',
            '-q:v', '2',
            $outputPath,
        ]);

        $process->setTimeout(120);
        $process->mustRun();
    }

    public function transcodeToHls(string $inputPath, string $outputDir, HlsProfile $profile): array
    {
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
            throw new VideoProcessingException('transcode_error', 'Unable to create HLS output directory.');
        }

        $playlistPath = $outputDir.'/playlist.m3u8';
        $segmentPattern = $outputDir.'/segment_%04d.ts';

        $process = new Process([
            $this->ffmpegPath,
            '-y',
            '-i', $inputPath,
            '-vf', 'scale=-2:'.$profile->height,
            '-c:v', 'libx264',
            '-b:v', $profile->videoBitrateKbps.'k',
            '-c:a', 'aac',
            '-b:a', $profile->audioBitrateKbps.'k',
            '-f', 'hls',
            '-hls_time', '2',
            '-hls_list_size', '0',
            '-hls_segment_filename', $segmentPattern,
            $playlistPath,
        ]);

        $process->setTimeout(600);
        $process->mustRun();

        $segments = glob($outputDir.'/segment_*.ts') ?: [];

        return $segments;
    }

    public function buildMasterPlaylist(array $variants, string $masterPath): void
    {
        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];

        foreach ($variants as $bandwidth => $playlistPath) {
            $lines[] = '#EXT-X-STREAM-INF:BANDWIDTH='.$bandwidth;
            $lines[] = basename($playlistPath);
        }

        $dir = dirname($masterPath);

        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new VideoProcessingException('transcode_error', 'Unable to create master playlist directory.');
        }

        file_put_contents($masterPath, implode("\n", $lines)."\n");
    }
}
