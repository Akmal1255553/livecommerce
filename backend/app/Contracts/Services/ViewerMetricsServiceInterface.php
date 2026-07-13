<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\LiveViewerMetric;
use App\Models\User;

interface ViewerMetricsServiceInterface
{
    public function initialize(string $liveSessionId): LiveViewerMetric;

    public function join(string $liveSessionId, User $user): LiveViewerMetric;

    public function leave(string $liveSessionId, User $user): LiveViewerMetric;

    public function get(string $liveSessionId): LiveViewerMetric;
}
