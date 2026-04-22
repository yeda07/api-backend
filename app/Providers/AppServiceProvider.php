<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $limit = $user
                ? (int) config('performance.rate_limit_per_minute', 240)
                : (int) config('performance.guest_rate_limit_per_minute', 60);

            $identifier = $user?->uid
                ?? $request->bearerToken()
                ?? $request->ip();

            return Limit::perMinute($limit)->by($identifier);
        });
    }
}
