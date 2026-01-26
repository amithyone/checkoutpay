<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // OPTIMIZED: Cache page data to avoid database query on every request
        $page = \Illuminate\Support\Facades\Cache::remember(
            'page_home',
            86400, // Cache for 24 hours (homepage rarely changes)
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
        // Settings are already cached in the model, but loading them here ensures single cache lookup
        $settings = [
            'site_favicon' => \App\Models\Setting::get('site_favicon'),
            'site_logo' => \App\Models\Setting::get('site_logo'),
            'site_name' => \App\Models\Setting::get('site_name', 'CheckoutPay'),
        ];
        
        $response = response()->view('home', [
            'page' => $page,
            'content' => $content,
            'settings' => $settings, // Pass settings to view
        ]);

        // Add HTTP cache headers for fast server performance
        // Browser/CDN will cache this page for 1 hour
        $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=3600');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
