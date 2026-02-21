<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    // public function boot(): void
    // {
    //     //
    // }
    public function boot()
    {
        // Prevent Sanctum from redirecting on API requests
        EnsureFrontendRequestsAreStateful::macro('shouldPassThrough', function ($request) {
            return $request->expectsJson() || $request->is('api/*');
        });
    }
}
