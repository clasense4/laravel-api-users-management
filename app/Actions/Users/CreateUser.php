<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * Create a user, commit the transaction, then dispatch post-commit side effects.
     *
     * The event is dispatched after commit so listeners never read uncommitted data.
     * Database commit and queue publication are not atomic; see README for the known
     * failure window and future mitigation options.
     *
     * @param  array{email: string, password: string, name: string}  $data
     */
    public function execute(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            return User::create([
                'email' => $data['email'],
                'password' => $data['password'], // hashed via Model cast
                'name' => $data['name'],
                'role' => UserRole::User,
                'active' => true,
            ]);
        });

        UserCreated::dispatch($user);

        return $user;
    }
}
