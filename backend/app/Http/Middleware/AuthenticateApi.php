<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Domain\UnauthorizedException;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $this->jwtService->authenticate($token);

        if ($user === null) {
            throw new UnauthorizedException;
        }

        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);

        return $next($request);
    }
}
