<?php

declare(strict_types=1);

use App\Services\Health\HealthService;

test('health service returns structured status data', function () {
    $service = app(HealthService::class);
    $status = $service->check();

    expect($status->status)->toBeIn(['ok', 'degraded'])
        ->and($status->database)->toBeString()
        ->and($status->redis)->toBeString()
        ->and($status->queue)->toBeString()
        ->and($status->toArray())->toHaveKeys(['status', 'database', 'redis', 'queue']);
});
