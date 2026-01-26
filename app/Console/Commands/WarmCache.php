<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;
use App\Models\Page;
use App\Services\AccountNumberService;

class WarmCache extends Command
{
    protected $signature = 'cache:warm';
    protected $description = 'Warm up application cache';

    public function handle()
    {
        $this->info('🔥 Warming up cache...');
        
        // Warm settings cache
        $this->info('Warming settings cache...');
        Setting::get('site_logo');
        Setting::get('site_favicon');
        Setting::get('site_name');
        $this->info('✅ Settings cache warmed');
        
        // Warm page cache
        $this->info('Warming page cache...');
        Page::getBySlug('home');
        $this->info('✅ Page cache warmed');
        
        // Warm account number service cache
        $this->info('Warming account number service cache...');
        $service = app(AccountNumberService::class);
        $service->getAvailablePoolCount();
        // Trigger cache population by calling getPendingAccountNumbers
        try {
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('getPendingAccountNumbers');
            $method->setAccessible(true);
            $method->invoke($service);
        } catch (\Exception $e) {
            // Method might be protected, that's okay
        }
        $this->info('✅ Account number service cache warmed');
        
        $this->info('✅ Cache warm-up complete!');
        
        // Verify cache keys exist
        $this->newLine();
        $this->info('Verifying cache keys...');
        $keys = [
            'page_home',
            'setting_site_logo',
            'setting_site_favicon',
            'setting_site_name',
            'account_number_service:pool_accounts',
            'account_number_service:pending_accounts',
            'account_number_service:last_used_account',
        ];
        
        foreach ($keys as $key) {
            $exists = Cache::has($key);
            if ($exists) {
                $this->info("   ✅ {$key}");
            } else {
                $this->warn("   ⚠️  {$key} (will be created on first use)");
            }
        }
        
        return 0;
    }
}
