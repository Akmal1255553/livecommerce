<?php

declare(strict_types=1);

namespace App\Contracts\VideoProcessing;

use App\DTOs\Video\HlsProfile;
use App\DTOs\Video\VideoProbeResult;

interface FfmpegTranscoderInterface
{
    public function probe(string $inputPath): VideoProbeResult;

    public function extractThumbnail(string $inputPath, string $outputPath, int $atSecond = 1): void;

    /**
     * @return list<string> Generated segment file paths relative to output directory
     */
    public function transcodeToHls(string $inputPath, string $outputDir, HlsProfile $profile): array;

    /**
     * @param  array<string, string>  $variants  Map of label => playlist path
     */
    public function buildMasterPlaylist(array $variants, string $masterPath): void;
}
