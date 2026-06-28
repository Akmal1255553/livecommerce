<?php

declare(strict_types=1);

return [
    'engine' => env('RECOMMENDATION_ENGINE', 'rule'),

    'scoring' => [
        'weights' => [
            'completion' => (float) env('REC_WEIGHT_COMPLETION', 0.40),
            'watch_time' => (float) env('REC_WEIGHT_WATCH_TIME', 0.20),
            'like' => (float) env('REC_WEIGHT_LIKE', 0.15),
            'comment' => (float) env('REC_WEIGHT_COMMENT', 0.10),
            'share' => (float) env('REC_WEIGHT_SHARE', 0.10),
            'freshness' => (float) env('REC_WEIGHT_FRESHNESS', 0.05),
        ],
        'feeds' => [
            'trending' => [],
            'popular' => [],
            'for_you' => [],
        ],
    ],

    'exploration' => [
        'exploit_ratio' => 0.90,
        'explore_ratio' => 0.10,
        'slot_positions' => [3, 8, 15],
        'max_views' => 1000,
        'max_age_days' => 7,
    ],

    'diversity' => [
        'max_consecutive_same_author' => 2,
        'max_consecutive_same_category' => 3,
        'min_gap_same_video_hours' => 72,
    ],

    'cache' => [
        'trending_ttl' => 300,
        'popular_ttl' => 900,
        'for_you_ttl' => 300,
        'snapshot_size' => 500,
    ],

    'rollup' => [
        'aggregation_interval_minutes' => 15,
        'trending_window_hours' => 24,
        'for_you_window_days' => 7,
    ],

    'filtering' => [
        'completed_exclude_days' => 30,
        'skip_exclude_days' => 7,
        'skip_max_watched_seconds' => 3,
    ],
];
