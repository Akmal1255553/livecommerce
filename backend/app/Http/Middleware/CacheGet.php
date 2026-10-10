<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches successful GET responses in the cache store for a short TTL.
 *
 * Only anonymous requests are cached — the middleware must run after
 * `auth.api.optional` so it can skip authenticated (personalized) responses
 * and never leak viewer-specific flags (is_liked, is_bookmarked) between users.
 *
 * Usage: `->middleware('cache.get:feed,10')` (prefix, TTL seconds).
 */
class CacheGet
{
    public function handle(Request $request, Closure $next, string $prefix = 'get', int $ttl = 15): Response
    {
        $isAnonymous = $request->user() === null && $request->bearerToken() === null;
        $key = $prefix.':'.md5($request->fullUrl().'|'.app()->getLocale());

        if ($isAnonymous) {
            $cached = Cache::get($key);
            if (is_string($cached)) {
                return response($cached, 200, [
                    'Content-Type' => 'application/json',
                    'X-Cache' => 'HIT',
                ]);
            }
        }

        $response = $next($request);

        if ($isAnonymous && $request->isMethod('GET') && $response->getStatusCode() === 200) {
            Cache::put($key, $response->getContent(), $ttl);
            $response->headers->set('X-Cache', 'MISS');
        }

        return $response;
    }
}
