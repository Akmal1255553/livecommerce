<?php

declare(strict_types=1);

return [

    'driver' => env('STORAGE_DRIVER', 'local'),

    'presigned_ttl_minutes' => (int) env('STORAGE_PRESIGNED_TTL', 15),

    'video_max_bytes' => (int) env('STORAGE_VIDEO_MAX_BYTES', 104_857_600),

    'video_upload_rate_limit' => (int) env('STORAGE_VIDEO_UPLOAD_RATE_LIMIT', 10),

    'video_upload_rate_decay_seconds' => (int) env('STORAGE_VIDEO_UPLOAD_RATE_DECAY', 3600),

];
