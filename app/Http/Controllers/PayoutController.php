<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('payout');
        
        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/payout',
                'contentClass' => 'payout-page-content',
                'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('payouts-collections'))],
            ]);
        }

        return view('payout.index');
    }
}
