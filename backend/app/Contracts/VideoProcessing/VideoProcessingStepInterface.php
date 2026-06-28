<?php

declare(strict_types=1);

namespace App\Contracts\VideoProcessing;

use App\Models\Video;

interface VideoProcessingStepInterface
{
    public function name(): \App\Enums\VideoProcessingStepName;

    public function run(Video $video): void;
}
