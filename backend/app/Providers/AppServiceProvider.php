<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\StructuredLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StructuredLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();

        // Scramble is require-dev — absent in production Docker (--no-dev).
        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::configure()
                ->withDocumentTransformers(function ($openApi): void {
                    $openApi->secure(
                        \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'JWT')
                    );
                });
        }
    }

    private function configureRateLimiting(): void
    {
        if ($this->app->environment('testing')) {
            RateLimiter::for('api', fn () => Limit::none());
            RateLimiter::for('auth', fn () => Limit::none());
            RateLimiter::for('checkout', fn () => Limit::none());
            RateLimiter::for('live-chat', fn () => Limit::none());
            RateLimiter::for('live-poll', fn () => Limit::none());
            RateLimiter::for('search', fn () => Limit::none());

            return;
        }

        RateLimiter::for('api', function (Request $request) {
            // throttleApi runs BEFORE auth.api, so $request->user() is usually null.
            // Key by bearer token when present so logged-in clients are not capped at guest IP limits.
            return Limit::perMinute(300)->by($this->rateLimitKey('api', $request));
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by('auth:'.$request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            // Beta: allow retries after stale cart / validation without 429.
            return Limit::perMinute(60)->by($this->rateLimitKey('checkout', $request));
        });

        // POST chat only — anti-spam for message send.
        RateLimiter::for('live-chat', function (Request $request) {
            return Limit::perMinute(30)->by($this->rateLimitKey('live-chat', $request));
        });

        // GET chat polling while watching a live room (~12–20 req/min typical).
        RateLimiter::for('live-poll', function (Request $request) {
            return Limit::perMinute(120)->by($this->rateLimitKey('live-poll', $request));
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($this->rateLimitKey('search', $request));
        });
    }

    /**
     * Prefer authenticated user id; else hash of Bearer JWT (auth runs after throttle);
     * else IP for true guests.
     */
    private function rateLimitKey(string $prefix, Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            return $prefix.':user:'.$user->getAuthIdentifier();
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return $prefix.':token:'.hash('sha256', $bearer);
        }

        return $prefix.':ip:'.$request->ip();
    }
}
