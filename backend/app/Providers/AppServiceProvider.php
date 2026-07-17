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
            $user = $request->user();

            if ($user !== null) {
                // Live room polls session + chat; 60/min was too tight for MVP clients.
                return Limit::perMinute(180)->by('user:'.$user->getAuthIdentifier());
            }

            return Limit::perMinute(20)->by('ip:'.$request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by('auth:'.$request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            $user = $request->user();
            $key = $user !== null
                ? 'checkout:user:'.$user->getAuthIdentifier()
                : 'checkout:ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        // POST chat only — anti-spam for message send.
        RateLimiter::for('live-chat', function (Request $request) {
            $user = $request->user();
            $key = $user !== null
                ? 'live-chat:user:'.$user->getAuthIdentifier()
                : 'live-chat:ip:'.$request->ip();

            return Limit::perMinute(30)->by($key);
        });

        // GET chat polling while watching a live room (~12–20 req/min typical).
        RateLimiter::for('live-poll', function (Request $request) {
            $user = $request->user();
            $key = $user !== null
                ? 'live-poll:user:'.$user->getAuthIdentifier()
                : 'live-poll:ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        RateLimiter::for('search', function (Request $request) {
            $user = $request->user();
            $key = $user !== null
                ? 'search:user:'.$user->getAuthIdentifier()
                : 'search:ip:'.$request->ip();

            return Limit::perMinute(30)->by($key);
        });
    }
}
