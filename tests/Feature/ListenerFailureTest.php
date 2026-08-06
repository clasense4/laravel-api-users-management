<?php

use App\Events\UserCreated;
use App\Listeners\NotifyAdministrator;
use App\Listeners\SendAccountCreatedEmail;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Notifications\NewUserRegistered;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

test('NotifyAdministrator skips and logs warning when admin email is not configured', function (): void {
    Config::set('app.admin_email', null);
    Log::spy();

    $user = User::factory()->create();
    $event = new UserCreated($user);
    $listener = new NotifyAdministrator;

    $listener->handle($event);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Administrator email not configured'));
});

test('NotifyAdministrator sends notification when admin email is configured', function (): void {
    Config::set('app.admin_email', 'admin@example.com');
    Notification::fake();

    $user = User::factory()->create();
    $event = new UserCreated($user);
    $listener = new NotifyAdministrator;

    $listener->handle($event);

    Notification::assertSentOnDemand(NewUserRegistered::class);
});

test('SendAccountCreatedEmail sends notification to the user', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $event = new UserCreated($user);
    $listener = new SendAccountCreatedEmail;

    $listener->handle($event);

    Notification::assertSentTo($user, AccountCreated::class);
});

test('SendAccountCreatedEmail has 3 retry attempts configured', function (): void {
    $listener = new SendAccountCreatedEmail;
    expect($listener->tries)->toBe(3);
});

test('SendAccountCreatedEmail has progressive backoff configured', function (): void {
    $listener = new SendAccountCreatedEmail;
    expect($listener->backoff())->toBe([10, 60, 300]);
});

test('NotifyAdministrator has 3 retry attempts configured', function (): void {
    $listener = new NotifyAdministrator;
    expect($listener->tries)->toBe(3);
});

test('NotifyAdministrator has progressive backoff configured', function (): void {
    $listener = new NotifyAdministrator;
    expect($listener->backoff())->toBe([10, 60, 300]);
});
