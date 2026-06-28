<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Video;
use Illuminate\Support\Collection;

interface VideoRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Video;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Video $video, array $attributes): Video;

    /**
     * @param  array{id: string, created_at: string}|null  $cursor
     * @param  list<string>|null  $userIds
     * @return Collection<int, Video>
     */
    public function cursorPaginateFeed(?array $cursor, int $limit, ?array $userIds = null): Collection;

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Video>
     */
    public function findPublishedByIds(array $ids): Collection;

    /**
     * @return Collection<int, Video>
     */
    public function listExplorationCandidates(int $limit): Collection;

    /**
     * @param  array{id: string, created_at: string}|null  $cursor
     * @return Collection<int, Video>
     */
    public function listNewCandidates(int $limit, ?array $cursor = null): Collection;
}
