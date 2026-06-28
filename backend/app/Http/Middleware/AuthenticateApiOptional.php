<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiOptional
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            $user = $this->jwtService->authenticate($token);

            if ($user !== null) {
                $request->setUserResolver(fn () => $user);
                auth()->setUser($user);
            }
        }

        return $next($request);
    }
}
