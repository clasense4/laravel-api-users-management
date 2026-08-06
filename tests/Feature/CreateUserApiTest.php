<?php

use App\Models\User;
use App\Notifications\AccountCreated;
use App\Notifications\NewUserRegistered;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

test('successful user creation returns 201 with expected fields', function (): void {
    $response = $this->postJson('/api/users', [
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'name' => 'New User',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'email', 'name', 'created_at'])
        ->assertJsonMissingPath('password')
        ->assertJsonFragment([
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
});

test('created user has hashed password in database', function (): void {
    $this->postJson('/api/users', [
        'email' => 'hash@example.com',
        'password' => 'plaintext1',
        'name' => 'Hash User',
    ]);

    $user = User::where('email', 'hash@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('plaintext1', $user->password))->toBeTrue();
    expect($user->password)->not->toBe('plaintext1');
});

test('password is not returned in response', function (): void {
    $response = $this->postJson('/api/users', [
        'email' => 'secret@example.com',
        'password' => 'password123',
        'name' => 'Secret User',
    ]);

    $response->assertJsonMissingPath('password');
    expect($response->json())->not->toHaveKey('data.password');
});

test('default role is user', function (): void {
    $this->postJson('/api/users', [
        'email' => 'role@example.com',
        'password' => 'password123',
        'name' => 'Role User',
    ]);

    $user = User::where('email', 'role@example.com')->first();
    expect($user->role->value)->toBe('user');
});

test('default active state is true', function (): void {
    $this->postJson('/api/users', [
        'email' => 'active@example.com',
        'password' => 'password123',
        'name' => 'Active User',
    ]);

    $user = User::where('email', 'active@example.com')->first();
    expect($user->active)->toBeTrue();
});

test('missing email returns 422', function (): void {
    $this->postJson('/api/users', [
        'password' => 'password123',
        'name' => 'No Email',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('invalid email format returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'not-an-email',
        'password' => 'password123',
        'name' => 'Bad Email',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('duplicate email returns 422', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/users', [
        'email' => 'existing@example.com',
        'password' => 'password123',
        'name' => 'Duplicate',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

test('missing password returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'no-pass@example.com',
        'name' => 'No Password',
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);
});

test('password shorter than 8 characters returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'short@example.com',
        'password' => 'abc1234',
        'name' => 'Short Pass',
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);
});

test('missing name returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'no-name@example.com',
        'password' => 'password123',
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('name shorter than 3 characters returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'short-name@example.com',
        'password' => 'password123',
        'name' => 'AB',
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('name longer than 50 characters returns 422', function (): void {
    $this->postJson('/api/users', [
        'email' => 'long-name@example.com',
        'password' => 'password123',
        'name' => str_repeat('A', 51),
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

test('new user confirmation notification is sent to the created user', function (): void {
    $this->postJson('/api/users', [
        'email' => 'notify@example.com',
        'password' => 'password123',
        'name' => 'Notify User',
    ]);

    $user = User::where('email', 'notify@example.com')->first();
    Notification::assertSentTo($user, AccountCreated::class);
});

test('administrator notification is sent to configured admin email', function (): void {
    Config::set('app.admin_email', 'admin@example.com');

    $this->postJson('/api/users', [
        'email' => 'admin-notify@example.com',
        'password' => 'password123',
        'name' => 'Admin Notify',
    ]);

    Notification::assertSentOnDemand(
        NewUserRegistered::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'admin@example.com';
        }
    );
});

test('no notifications sent when validation fails', function (): void {
    $this->postJson('/api/users', [
        'email' => 'bad',
        'password' => '123',
        'name' => 'X',
    ]);

    Notification::assertNothingSent();
});

test('response includes X-Request-ID header', function (): void {
    $response = $this->postJson('/api/users', [
        'email' => 'reqid@example.com',
        'password' => 'password123',
        'name' => 'ReqId User',
    ]);

    $response->assertHeader('X-Request-ID');
});

test('inbound X-Request-ID is echoed back', function (): void {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';

    $response = $this->postJson('/api/users', [
        'email' => 'echo@example.com',
        'password' => 'password123',
        'name' => 'Echo User',
    ], ['X-Request-ID' => $uuid]);

    $response->assertHeader('X-Request-ID', $uuid);
});
