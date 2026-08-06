<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Administrator — can edit any user
        $admin = User::factory()->administrator()->create([
            'email' => 'admin@example.com',
            'name' => 'Admin User',
            'password' => 'password123',
        ]);
        Order::factory()->count(3)->create(['user_id' => $admin->id]);

        // Manager — can edit users with role=user
        $manager = User::factory()->manager()->create([
            'email' => 'manager@example.com',
            'name' => 'Manager User',
            'password' => 'password123',
        ]);
        Order::factory()->count(5)->create(['user_id' => $manager->id]);

        // Regular users with various order counts
        $userA = User::factory()->create([
            'email' => 'user.a@example.com',
            'name' => 'Alice User',
            'password' => 'password123',
        ]);
        Order::factory()->count(10)->create(['user_id' => $userA->id]);

        $userB = User::factory()->create([
            'email' => 'user.b@example.com',
            'name' => 'Bob User',
            'password' => 'password123',
        ]);
        // Bob has zero orders

        // Inactive user — should never appear in GET /api/users results
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'name' => 'Inactive User',
            'password' => 'password123',
        ]);

        // Additional random users for pagination testing
        User::factory()->count(20)->create()->each(function (User $user): void {
            Order::factory()->count(fake()->numberBetween(0, 8))->create(['user_id' => $user->id]);
        });
    }
}
