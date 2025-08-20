<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user for testing
        User::firstOrCreate(
            ['email' => 'admin@checkright.test'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@checkright.test',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'tenant_id' => null, // Super admin is not tenant-scoped
            ]
        );
    }
}
