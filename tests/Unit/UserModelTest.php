<?php

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('user can be created with required fields', function (): void {
    $user = User::create([
        'email' => 'test@example.com',
        'password' => 'password123',
        'name' => 'Test User',
    ]);

    expect($user->id)->toBeInt();
    expect($user->email)->toBe('test@example.com');
    expect($user->name)->toBe('Test User');
});

test('email must be unique', function (): void {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => User::factory()->create(['email' => 'duplicate@example.com']))
        ->toThrow(QueryException::class);
});

test('password is hashed and not stored in plaintext', function (): void {
    $user = User::create([
        'email' => 'hash@example.com',
        'password' => 'plaintext',
        'name' => 'Hash Test',
    ]);

    expect($user->password)->not->toBe('plaintext');
    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

test('default role is user', function (): void {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::User);
});

test('default active state is true', function (): void {
    $user = User::factory()->create();

    expect($user->active)->toBeTrue();
});

test('password is hidden from serialized output', function (): void {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
});

test('user has many orders', function (): void {
    $user = User::factory()->create();
    Order::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->orders)->toHaveCount(3);
});

test('orders count is accurate via withCount', function (): void {
    $user = User::factory()->create();
    Order::factory()->count(5)->create(['user_id' => $user->id]);

    $user = User::withCount('orders')->find($user->id);

    expect($user->orders_count)->toBe(5);
});

test('order belongs to user', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    expect($order->user->id)->toBe($user->id);
});
