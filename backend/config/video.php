<?php

declare(strict_types=1);

return [

    'max_duration_seconds' => (int) env('VIDEO_MAX_DURATION_SECONDS', 60),

    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),

    'hls_profiles' => [
        '720p' => [
            'name' => '720p',
            'height' => 720,
            'video_bitrate_kbps' => 2500,
            'audio_bitrate_kbps' => 128,
        ],
        '480p' => [
            'name' => '480p',
            'height' => 480,
            'video_bitrate_kbps' => 1000,
            'audio_bitrate_kbps' => 96,
        ],
    ],

];
