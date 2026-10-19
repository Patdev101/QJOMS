<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The first account, for signing in.
 * Run: php artisan db:seed --class=AdminUserSeeder   (safe to run again)
 * Change this password once the system is used for real.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password'], // hashed automatically by the User model
        );
    }
}
