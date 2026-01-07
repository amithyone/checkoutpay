<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Payment Settings
        Setting::set(
            'payment_time_window_minutes',
            15,
            'integer',
            'payment',
            'Maximum time window (in minutes) for matching emails with payment requests. Emails received after this time will not be matched.'
        );
    }
}
