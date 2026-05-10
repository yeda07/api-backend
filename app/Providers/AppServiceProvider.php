<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $baseUrl = rtrim((string) env('PASSWORD_RESET_URL', rtrim((string) config('app.url'), '/') . '/auth/reset-password'), '/');

            return $baseUrl . '?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

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

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'anonymous'));

            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });
    }
}
