<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enforce valid role values at the database layer so raw SQL, imports,
        // or other services cannot insert an invalid role.
        //
        // SQLite does not support ALTER TABLE ... ADD CONSTRAINT after creation,
        // so the constraint is only applied on PostgreSQL. The PHP enum cast
        // (UserRole) still protects all Eloquent operations on SQLite.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('administrator', 'manager', 'user'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
        }
    }
};
