<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@paymentgateway.com',
            'password' => Hash::make('password'), // Change this in production!
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        Admin::create([
            'name' => 'Support Admin',
            'email' => 'support@paymentgateway.com',
            'password' => Hash::make('password'), // Change this in production!
            'role' => Admin::ROLE_SUPPORT,
            'is_active' => true,
        ]);
    }
}
