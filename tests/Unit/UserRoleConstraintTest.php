<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('valid roles are accepted by the model', function (): void {
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        expect($user->role)->toBe($role);
    }
});

test('role is cast to UserRole enum', function (): void {
    $user = User::factory()->create(['role' => UserRole::Administrator]);

    // Re-fetch from DB to confirm the cast survives a round-trip
    $fresh = User::find($user->id);
    expect($fresh->role)->toBeInstanceOf(UserRole::class);
    expect($fresh->role)->toBe(UserRole::Administrator);
});

test('invalid role is rejected by the database on PostgreSQL', function (): void {
    // SQLite does not support ADD CONSTRAINT after creation, so the DB-level
    // check only applies on PostgreSQL. On SQLite the PHP enum cast is the guard.
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Role check constraint is only enforced on PostgreSQL.');
    }

    expect(fn () => DB::table('users')->insert([
        'email' => 'bad@example.com',
        'password' => bcrypt('password'),
        'name' => 'Bad Role',
        'role' => 'superadmin', // invalid
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
