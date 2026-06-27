<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Contracts\Services\HealthServiceInterface;
use App\DTOs\Health\HealthStatusData;
use App\Logging\StructuredLogger;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthService extends BaseService implements HealthServiceInterface
{
    public function check(): HealthStatusData
    {
        $database = $this->checkDatabase();
        $redis = $this->checkRedis();
        $queue = $this->checkQueue();

        $healthy = $database === 'connected';
        $status = $healthy ? 'ok' : 'degraded';

        $this->logger->info('health.check', [
            'status' => $status,
            'database' => $database,
            'redis' => $redis,
            'queue' => $queue,
        ]);

        return new HealthStatusData(
            status: $status,
            database: $database,
            redis: $redis,
            queue: $queue,
        );
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'connected';
        } catch (\Throwable) {
            return 'disconnected';
        }
    }

    private function checkRedis(): string
    {
        try {
            if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
                return 'not_configured';
            }

            Redis::connection()->ping();

            return 'connected';
        } catch (\Throwable) {
            return 'disconnected';
        }
    }

    private function checkQueue(): string
    {
        try {
            $connection = config('queue.default');

            if ($connection === 'sync') {
                return 'running';
            }

            Queue::connection()->size();

            return 'running';
        } catch (\Throwable) {
            return 'disconnected';
        }
    }
}
