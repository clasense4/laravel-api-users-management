<?php

use App\Models\User;
use App\Notifications\AccountCreated;
use App\Notifications\NewUserRegistered;

test('AccountCreated mail is addressed to the new user', function (): void {
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $notification = new AccountCreated($user);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Welcome');
    expect($mail->introLines)->not->toBeEmpty();
});

test('AccountCreated notification delivers via mail channel', function (): void {
    $user = User::factory()->create();
    $notification = new AccountCreated($user);

    expect($notification->via($user))->toBe(['mail']);
});

test('NewUserRegistered mail contains new user details', function (): void {
    $user = User::factory()->create([
        'name' => 'Bob Smith',
        'email' => 'bob@example.com',
    ]);

    $notification = new NewUserRegistered($user);
    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('New user registered');

    $allLines = implode(' ', $mail->introLines);
    expect($allLines)->toContain('Bob Smith');
    expect($allLines)->toContain('bob@example.com');
    expect($allLines)->not->toContain('password');
});

test('NewUserRegistered notification delivers via mail channel', function (): void {
    $user = User::factory()->create();
    $notification = new NewUserRegistered($user);

    expect($notification->via(new stdClass))->toBe(['mail']);
});
