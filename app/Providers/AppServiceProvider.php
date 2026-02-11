<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensure View is available (fixes "Target class [view] does not exist" on some servers)
        if (!$this->app->bound('view')) {
            $this->app->register(\Illuminate\View\ViewServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(60)->by($user ? $user->id : $request->ip());
        });
    }
}
