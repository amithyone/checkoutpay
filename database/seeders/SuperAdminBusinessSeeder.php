<?php

namespace Database\Seeders;

use App\Services\SuperAdminBusinessService;
use Illuminate\Database\Seeder;

class SuperAdminBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating super admin business...');
        
        $business = SuperAdminBusinessService::getOrCreateSuperAdminBusiness();
        
        $this->command->info("Super admin business created/verified:");
        $this->command->info("  - ID: {$business->id}");
        $this->command->info("  - Name: {$business->name}");
        $this->command->info("  - Email: {$business->email}");
        
        $website = SuperAdminBusinessService::getSuperAdminWebsite();
        if ($website) {
            $this->command->info("  - Website: {$website->website_url}");
        }
        
        $this->command->info('Super admin business setup complete!');
    }
}
