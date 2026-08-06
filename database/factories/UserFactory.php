<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // hashed by model cast
            'role' => UserRole::User,
            'active' => true,
        ];
    }

    public function administrator(): static
    {
        return $this->state(['role' => UserRole::Administrator]);
    }

    public function manager(): static
    {
        return $this->state(['role' => UserRole::Manager]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function withPassword(string $password): static
    {
        return $this->state(['password' => $password]);
    }
}
