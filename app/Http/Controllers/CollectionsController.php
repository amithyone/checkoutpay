<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class CollectionsController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('collections');
        
        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/collections',
                'contentClass' => 'collections-page-content',
                'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payouts-collections'))],
            ]);
        }

        return view('collections.index');
    }
}
