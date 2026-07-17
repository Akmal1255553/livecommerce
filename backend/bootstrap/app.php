<?php

declare(strict_types=1);

use App\Exceptions\BusinessException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApi;
use App\Http\Middleware\AuthenticateApiOptional;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSeller;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\ApiResponse;
use App\Jobs\AggregateEngagementRollupsJob;
use App\Jobs\CompleteDeliveredOrdersJob;
use App\Jobs\FlushVideoViewsJob;
use App\Jobs\RefreshPopularCacheJob;
use App\Jobs\RefreshTrendingCacheJob;
use App\Jobs\ReleaseExpiredInventoryReservationsJob;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => AuthenticateApi::class,
            'auth.api.optional' => AuthenticateApiOptional::class,
            'seller' => EnsureSeller::class,
            'role' => EnsureRole::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->api(prepend: [
            AssignRequestId::class,
            LogApiRequest::class,
            SetLocale::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new FlushVideoViewsJob)->everyMinute();
        $schedule->job(new AggregateEngagementRollupsJob)->everyFifteenMinutes();
        $schedule->job(new RefreshTrendingCacheJob)->everyFiveMinutes();
        $schedule->job(new RefreshPopularCacheJob)->everyFifteenMinutes();
        $schedule->job(new CompleteDeliveredOrdersJob)->daily();
        $schedule->job(new ReleaseExpiredInventoryReservationsJob)->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getErrors(),
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Unauthenticated.',
                401,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Resource not found.', 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Too many requests.', 429);
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Request failed.',
                $exception->getStatusCode(),
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return null;
            }

            report($exception);

            return ApiResponse::error('Internal server error.', 500);
        });
    })->create();
