<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\StructuredLogger;
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
}
