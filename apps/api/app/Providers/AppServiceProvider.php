<?php

namespace App\Providers;

use App\Services\Security\DeviceFingerprintService;
use App\Services\Security\RequestSignatureValidator;
use App\Services\Security\SecurityLogger;
use App\Services\Security\TokenManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind security services
        $this->app->singleton(DeviceFingerprintService::class);
        $this->app->singleton(SecurityLogger::class);
        $this->app->singleton(TokenManager::class);

        $this->app->singleton(RequestSignatureValidator::class, function ($app) {
            return new RequestSignatureValidator($app->make(DeviceFingerprintService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
