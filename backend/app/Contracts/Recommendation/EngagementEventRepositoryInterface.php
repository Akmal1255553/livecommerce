<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

interface EngagementEventRepositoryInterface
{
    /**
     * @return list<string>
     */
    public function completedVideoIdsForUser(string $userId, int $days): array;

    /**
     * @return list<string>
     */
    public function skippedVideoIdsForUser(string $userId, int $days, int $maxWatchedSeconds): array;
}
