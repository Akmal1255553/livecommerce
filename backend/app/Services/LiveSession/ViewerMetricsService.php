<?php

declare(strict_types=1);

namespace App\Services\LiveSession;

use App\Contracts\Services\ViewerMetricsServiceInterface;
use App\Logging\StructuredLogger;
use App\Models\LiveSession;
use App\Models\LiveViewerMetric;
use App\Models\LiveViewerPresence;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class ViewerMetricsService extends BaseService implements ViewerMetricsServiceInterface
{
    public function __construct(StructuredLogger $logger)
    {
        parent::__construct($logger);
    }

    public function initialize(string $liveSessionId): LiveViewerMetric
    {
        return LiveViewerMetric::query()->firstOrCreate(
            ['live_session_id' => $liveSessionId],
            [
                'current_viewers' => 0,
                'peak_viewers' => 0,
                'unique_viewers' => 0,
            ],
        );
    }

    public function join(string $liveSessionId, User $user): LiveViewerMetric
    {
        return DB::transaction(function () use ($liveSessionId, $user): LiveViewerMetric {
            $metrics = $this->initialize($liveSessionId);

            $presence = LiveViewerPresence::query()->firstOrNew([
                'live_session_id' => $liveSessionId,
                'user_id' => $user->id,
            ]);

            $isNewUnique = ! $presence->exists;
            $wasAway = $presence->exists && $presence->left_at !== null;

            if ($isNewUnique || $wasAway || ! $presence->exists) {
                if (! $presence->exists || $presence->left_at !== null) {
                    $presence->joined_at = now();
                    $presence->left_at = null;
                    $presence->save();

                    $metrics->current_viewers = (int) $metrics->current_viewers + 1;
                    if ($isNewUnique) {
                        $metrics->unique_viewers = (int) $metrics->unique_viewers + 1;
                    }
                    $metrics->peak_viewers = max(
                        (int) $metrics->peak_viewers,
                        (int) $metrics->current_viewers,
                    );
                    $metrics->save();
                }
            }

            LiveSession::query()->whereKey($liveSessionId)->update([
                'viewer_count' => $metrics->current_viewers,
            ]);

            return $metrics->fresh() ?? $metrics;
        });
    }

    public function leave(string $liveSessionId, User $user): LiveViewerMetric
    {
        return DB::transaction(function () use ($liveSessionId, $user): LiveViewerMetric {
            $metrics = $this->initialize($liveSessionId);

            $presence = LiveViewerPresence::query()
                ->where('live_session_id', $liveSessionId)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();

            if ($presence !== null) {
                $presence->left_at = now();
                $presence->save();

                $metrics->current_viewers = max(0, (int) $metrics->current_viewers - 1);
                $metrics->save();
            }

            LiveSession::query()->whereKey($liveSessionId)->update([
                'viewer_count' => $metrics->current_viewers,
            ]);

            return $metrics->fresh() ?? $metrics;
        });
    }

    public function get(string $liveSessionId): LiveViewerMetric
    {
        return $this->initialize($liveSessionId);
    }
}
