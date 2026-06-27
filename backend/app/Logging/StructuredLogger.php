<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Log;

final class StructuredLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log('info', $message, $context, $channel);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log('warning', $message, $context, $channel);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log('error', $message, $context, $channel);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context, ?string $channel): void
    {
        $logger = Log::channel($channel ?? config('logging.default'));

        $requestId = request()->attributes->get('request_id')
            ?? request()->header('X-Request-Id');

        $logger->{$level}($message, array_merge([
            'request_id' => $requestId,
        ], $context));
    }
}
