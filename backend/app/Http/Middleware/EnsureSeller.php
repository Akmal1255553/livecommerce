<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Enums\UserRole;
use App\Exceptions\Domain\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeller
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(UserRole::Seller)) {
            throw new ForbiddenException('Seller access required.');
        }

        $store = $this->stores->findActiveByUser($user);

        if ($store === null) {
            throw new ForbiddenException('Active store required.');
        }

        $request->attributes->set('store', $store);

        return $next($request);
    }
}
