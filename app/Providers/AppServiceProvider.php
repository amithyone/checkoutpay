<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use App\Notifications\Channels\TelegramChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Telegram notification channel
        $this->app->make(ChannelManager::class)->extend('telegram', function ($app) {
            return new TelegramChannel();
        });
    }
}
