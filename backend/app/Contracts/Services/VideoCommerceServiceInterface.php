<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoProduct;
use Illuminate\Support\Collection;

interface VideoCommerceServiceInterface
{
    /**
     * @return Collection<int, VideoProduct>
     */
    public function listForVideo(string $videoId, ?User $viewer): Collection;

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return Collection<int, VideoProduct>
     */
    public function syncForVideo(User $owner, string $videoId, array $attachments): Collection;

    /**
     * @param  Collection<int, Video>  $videos
     * @return Collection<int, Video>
     */
    public function hydrateForVideos(Collection $videos, ?User $viewer): Collection;
}
