<?php

namespace App\Http\Controllers;

use App\Support\FaqCatalog;
use App\Support\Seo;
use Illuminate\View\View;

class SiteMapController extends Controller
{
    public function index(): View
    {
        $paths = config('seo.sitemap_paths', ['/']);
        sort($paths);

        $labels = [
            '/' => 'Home',
            '/pricing' => 'Pricing',
            '/faqs' => 'FAQs',
            '/wordpress-plugin' => 'WordPress plugin',
            '/api-docs' => 'API documentation',
            '/developers/program' => 'Developer program',
        ];

        return view('site-map.index', [
            'seo' => Seo::forPath('/site-map'),
            'paths' => $paths,
            'labels' => $labels,
            'faqCategories' => FaqCatalog::categories(),
        ]);
    }
}
