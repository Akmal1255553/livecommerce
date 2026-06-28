<?php

declare(strict_types=1);

namespace App\Contracts\VideoProcessing;

use App\Enums\VideoProcessingStepName;
use App\Models\Video;

interface VideoProcessingStepInterface
{
    public function name(): VideoProcessingStepName;

    public function run(Video $video): void;
}
