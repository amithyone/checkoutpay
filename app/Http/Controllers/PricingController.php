<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('pricing');
        
        if (!$page) {
            abort(404, 'Pricing page not found. Please run: php artisan db:seed --class=PageSeeder');
        }

        // Content is already cast to array by the model
        $content = is_array($page->content) ? $page->content : (json_decode($page->content, true) ?? []);
        
        return view('pricing', [
            'page' => $page,
            'content' => $content,
        ]);
    }
}
