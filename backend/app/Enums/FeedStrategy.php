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

    /**
     * @return list<string>
     */
    public function candidateSourceIds(): array
    {
        return match ($this) {
            self::Trending => ['trending'],
            self::Popular => ['popular'],
            self::New => ['new'],
            self::ForYou => ['trending', 'popular', 'new', 'bookmarks', 'exploration', 'category', 'seller'],
            self::Following => ['following'],
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
