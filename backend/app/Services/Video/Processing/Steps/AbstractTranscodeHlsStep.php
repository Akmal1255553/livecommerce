<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\DTOs\Video\HlsProfile;
use App\Enums\MediaAssetType;
use App\Models\Video;
use App\Services\Media\MediaAssetService;
use App\Services\Video\Processing\AbstractProcessingStep;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

abstract class AbstractTranscodeHlsStep extends AbstractProcessingStep
{
    public function __construct(
        VideoStateMachineInterface $stateMachine,
        protected readonly StorageServiceInterface $storage,
        protected readonly MediaServiceInterface $media,
        protected readonly FfmpegTranscoderInterface $transcoder,
        protected readonly MediaAssetService $assets,
    ) {
        parent::__construct($stateMachine);
    }

    abstract protected function rendition(): string;

    abstract protected function assetType(): MediaAssetType;

    abstract protected function profile(): HlsProfile;

    protected function execute(Video $video): void
    {
        $upload = $this->media->findUploadForVideo($video->id);

        if ($upload === null) {
            return;
        }

        $rawTemp = $this->storage->downloadToTemp($upload->storage_path);
        $outputDir = sys_get_temp_dir().'/lc_hls_'.$video->id.'_'.$this->rendition();

        if (is_dir($outputDir)) {
            $this->deleteDirectory($outputDir);
        }

        mkdir($outputDir, 0777, true);

        try {
            $this->transcoder->transcodeToHls($rawTemp, $outputDir, $this->profile());

            $prefix = $this->media->videoHlsRenditionDir($video->id, $this->rendition());

            foreach ($this->filesInDirectory($outputDir) as $file) {
                $relative = str_replace($outputDir.'/', '', $file->getPathname());
                $this->storage->putFile($prefix.'/'.$relative, $file->getPathname());
            }

            $playlistPath = $this->media->videoHlsRenditionPlaylistPath($video->id, $this->rendition());

            $this->assets->register(
                $video,
                $this->assetType(),
                $playlistPath,
                'application/vnd.apple.mpegurl',
                [
                    'metadata' => [
                        'rendition' => $this->rendition(),
                        'profile' => $this->profile()->name,
                    ],
                ],
            );
        } finally {
            @unlink($rawTemp);
            $this->deleteDirectory($outputDir);
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function filesInDirectory(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach ($this->filesInDirectory($directory) as $file) {
            @unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}
