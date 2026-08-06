<?php

namespace App\Providers;

use App\Events\UserCreated;
use App\Listeners\NotifyAdministrator;
use App\Listeners\SendAccountCreatedEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerEvents();
        $this->configureRateLimiting();
    }

    private function registerEvents(): void
    {
        Event::listen(UserCreated::class, SendAccountCreatedEmail::class);
        Event::listen(UserCreated::class, NotifyAdministrator::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
