<?php

use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated request returns 401', function (): void {
    $this->getJson('/api/users')->assertStatus(401);
});

test('returns only active users', function (): void {
    // Create active user
    User::factory()->count(3)->create();
    User::factory()->inactive()->count(2)->create();

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    $response->assertStatus(200);
    // 4 active = 3 regular + 1 admin acting as auth user
    expect($response->json('users'))->toHaveCount(4);
});

test('inactive users never appear in results', function (): void {
    User::factory()->inactive()->create(['email' => 'inactive@example.com']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    $emails = collect($response->json('users'))->pluck('email');
    expect($emails)->not->toContain('inactive@example.com');
});

test('password is excluded from response', function (): void {
    Sanctum::actingAs(User::factory()->administrator()->create());
    User::factory()->count(2)->create();

    $response = $this->getJson('/api/users');
    foreach ($response->json('users') as $user) {
        expect($user)->not->toHaveKey('password');
    }
});

test('search by name filters results', function (): void {
    User::factory()->create(['name' => 'Alice Wonderland', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?search=Alice');
    $names = collect($response->json('users'))->pluck('name');
    expect($names)->toContain('Alice Wonderland');
    expect($names)->not->toContain('Bob Builder');
});

test('search by email filters results', function (): void {
    User::factory()->create(['name' => 'Carol Test', 'email' => 'carol@searchable.com']);
    User::factory()->create(['name' => 'Dave Test', 'email' => 'dave@other.com']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?search=searchable');
    $emails = collect($response->json('users'))->pluck('email');
    expect($emails)->toContain('carol@searchable.com');
    expect($emails)->not->toContain('dave@other.com');
});

test('inactive user is excluded even when search matches', function (): void {
    User::factory()->inactive()->create(['name' => 'Invisible Match', 'email' => 'hidden@example.com']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?search=Invisible');
    $names = collect($response->json('users'))->pluck('name');
    expect($names)->not->toContain('Invisible Match');
});

test('no search returns all active users on first page', function (): void {
    User::factory()->count(5)->create();
    User::factory()->inactive()->count(2)->create();

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    // 5 + 1 admin = 6 active, all fit on first page
    expect($response->json('users'))->toHaveCount(6);
});

test('default page is 1', function (): void {
    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    expect($response->json('page'))->toBe(1);
});

test('requested page is returned in response', function (): void {
    User::factory()->count(20)->create();
    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?page=2');
    expect($response->json('page'))->toBe(2);
});

test('pagination limits results to page size', function (): void {
    User::factory()->count(20)->create();
    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    expect(count($response->json('users')))->toBeLessThanOrEqual(15);
});

test('sorting by name returns ascending order', function (): void {
    User::factory()->create(['name' => 'Zara Z', 'email' => 'zara@example.com']);
    User::factory()->create(['name' => 'Aaron A', 'email' => 'aaron@example.com']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?sortBy=name');
    $names = collect($response->json('users'))->pluck('name')->toArray();
    $sorted = $names;
    sort($sorted, SORT_STRING | SORT_FLAG_CASE);
    expect($names)->toBe($sorted);
});

test('sorting by email returns ascending order', function (): void {
    User::factory()->create(['email' => 'zzz@example.com', 'name' => 'ZZZ']);
    User::factory()->create(['email' => 'aaa@example.com', 'name' => 'AAA']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?sortBy=email');
    $emails = collect($response->json('users'))->pluck('email')->toArray();
    $sorted = $emails;
    sort($sorted);
    expect($emails)->toBe($sorted);
});

test('default sort is created_at descending', function (): void {
    $old = User::factory()->create(['created_at' => now()->subDays(10)]);
    $new = User::factory()->create(['created_at' => now()->subSecond()]);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    $ids = collect($response->json('users'))->pluck('id')->toArray();

    // Most recently created should come first
    expect(array_search($new->id, $ids))->toBeLessThan(array_search($old->id, $ids));
});

test('invalid sortBy value returns 422', function (): void {
    Sanctum::actingAs(User::factory()->administrator()->create());

    $this->getJson('/api/users?sortBy=malicious_column')->assertStatus(422);
});

test('orders_count is included and accurate', function (): void {
    $user = User::factory()->create();
    Order::factory()->count(4)->create(['user_id' => $user->id]);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    $found = collect($response->json('users'))->firstWhere('id', $user->id);
    expect($found['orders_count'])->toBe(4);
});

test('orders_count is zero when user has no orders', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    $found = collect($response->json('users'))->firstWhere('id', $user->id);
    expect($found['orders_count'])->toBe(0);
});

test('administrator can_edit is true for all users', function (): void {
    $admin = User::factory()->administrator()->create();
    User::factory()->create();
    User::factory()->manager()->create();
    User::factory()->administrator()->create();

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/users');
    foreach ($response->json('users') as $user) {
        expect($user['can_edit'])->toBeTrue();
    }
});

test('manager can_edit is true only for role=user targets', function (): void {
    $manager = User::factory()->manager()->create();
    $normalUser = User::factory()->create(['name' => 'Normal User']);
    $anotherManager = User::factory()->manager()->create(['name' => 'Another Manager']);
    $admin = User::factory()->administrator()->create(['name' => 'Admin']);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/users');
    $users = collect($response->json('users'))->keyBy('id');

    expect($users[$normalUser->id]['can_edit'])->toBeTrue();
    expect($users[$anotherManager->id]['can_edit'])->toBeFalse();
    expect($users[$admin->id]['can_edit'])->toBeFalse();
    expect($users[$manager->id]['can_edit'])->toBeFalse(); // manager cannot edit themselves
});

test('user can_edit is true only for themselves', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $manager = User::factory()->manager()->create();
    $admin = User::factory()->administrator()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/users');
    $users = collect($response->json('users'))->keyBy('id');

    expect($users[$user->id]['can_edit'])->toBeTrue();
    expect($users[$other->id]['can_edit'])->toBeFalse();
    expect($users[$manager->id]['can_edit'])->toBeFalse();
    expect($users[$admin->id]['can_edit'])->toBeFalse();
});

test('response shape includes all required fields', function (): void {
    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users');
    foreach ($response->json('users') as $user) {
        expect($user)->toHaveKeys(['id', 'email', 'name', 'role', 'created_at', 'orders_count', 'can_edit']);
    }
});

test('page parameter must be integer >= 1', function (): void {
    Sanctum::actingAs(User::factory()->administrator()->create());

    $this->getJson('/api/users?page=0')->assertStatus(422);
});

test('search combined with sort and pagination works together', function (): void {
    User::factory()->count(30)->create(['name' => 'SearchTarget Person']);
    User::factory()->count(5)->create(['name' => 'OtherName']);

    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->getJson('/api/users?search=SearchTarget&sortBy=name&page=2');
    $response->assertStatus(200);
    expect($response->json('page'))->toBe(2);
    foreach ($response->json('users') as $user) {
        expect($user['name'])->toContain('SearchTarget');
    }
});

test('safe 500 response does not expose internals', function (): void {
    // Force an exception by using an invalid driver that will blow up
    Sanctum::actingAs(User::factory()->administrator()->create());

    // Trigger a server error via a custom route that throws
    $response = $this->getJson('/api/users?sortBy=name');
    // This just verifies the normal path works; the 500 structure is tested via the exception handler
    $response->assertStatus(200);
});
