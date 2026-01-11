<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test business
        $business = Business::create([
            'name' => 'Test Business',
            'email' => 'business@test.com',
            'password' => Hash::make('password123'),
            'phone' => '+2348012345678',
            'address' => '123 Test Street, Lagos, Nigeria',
            'website' => 'https://test-business.com',
            'website_approved' => true,
            'is_active' => true,
            'balance' => 0.00,
        ]);

        $this->command->info('Business created successfully!');
        $this->command->info('Email: business@test.com');
        $this->command->info('Password: password123');
        $this->command->info('API Key: ' . $business->api_key);
    }
}
