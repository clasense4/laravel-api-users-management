<?php

namespace App\Providers;

use App\Events\UserCreated;
use App\Listeners\NotifyAdministrator;
use App\Listeners\SendAccountCreatedEmail;
use Illuminate\Support\Facades\Event;
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
    }

    private function registerEvents(): void
    {
        Event::listen(UserCreated::class, SendAccountCreatedEmail::class);
        Event::listen(UserCreated::class, NotifyAdministrator::class);
    }
}
