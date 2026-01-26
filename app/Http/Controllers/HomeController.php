<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // OPTIMIZED: Cache page data to avoid database query on every request
        $page = \Illuminate\Support\Facades\Cache::remember(
            'page_home',
            3600, // Cache for 1 hour
            function () {
                return Page::getBySlug('home');
            }
        );
        
        if (!$page) {
            abort(404, 'Home page not found. Please run: php artisan db:seed --class=PageSeeder');
        }

        // Content is already cast to array by the model
        $content = is_array($page->content) ? $page->content : (json_decode($page->content, true) ?? []);
        
        // OPTIMIZED: Pre-load all settings used in the view to avoid N+1 queries
        $settings = [
            'site_favicon' => \App\Models\Setting::get('site_favicon'),
            'site_logo' => \App\Models\Setting::get('site_logo'),
            'site_name' => \App\Models\Setting::get('site_name', 'CheckoutPay'),
        ];
        
        return view('home', [
            'page' => $page,
            'content' => $content,
            'settings' => $settings, // Pass settings to view
        ]);
    }
}
