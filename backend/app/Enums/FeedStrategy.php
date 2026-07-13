<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedStrategy: string
{
    case Trending = 'trending';
    case Popular = 'popular';
    case New = 'new';
    case ForYou = 'for_you';
    case Following = 'following';
    case Discover = 'discover';

    /**
     * @return list<string>
     */
    public function candidateSourceIds(): array
    {
        return match ($this) {
            self::Trending => ['trending'],
            self::Popular => ['popular'],
            self::New => ['new'],
            self::ForYou => ['trending', 'popular', 'new', 'bookmarks', 'exploration', 'category', 'seller', 'live'],
            self::Following => ['following'],
            self::Discover => ['trending', 'popular', 'new', 'bookmarks', 'exploration', 'category', 'seller', 'live'],
        };
    }

    public function usesEngine(): bool
    {
        return match ($this) {
            self::New, self::Following => false,
            default => true,
        };
    }
}
