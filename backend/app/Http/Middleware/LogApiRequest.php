<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\StructuredLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function __construct(
        private readonly StructuredLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $this->logger->info('api.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}
