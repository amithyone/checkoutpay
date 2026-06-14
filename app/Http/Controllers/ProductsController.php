<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\MarketingVirtualCard;
use Illuminate\View\View;

class ProductsController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'virtualCard' => MarketingVirtualCard::snapshot(),
        ]);
    }

    public function invoices(): View
    {
        // Check if editable page exists in admin
        $page = Page::where('slug', 'products-invoices')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/products/invoices',
                'contentClass' => 'invoices-page-content',
                'jsonLdExtra' => [\App\Support\FaqCatalog::faqPageJsonLd(\App\Support\FaqCatalog::forCategory('invoices-billing'))],
            ]);
        }

        // Fallback to static view
        return view('products.invoices');
    }

    public function memberships(): View
    {
        // Check if editable page exists in admin
        $page = Page::where('slug', 'products-memberships')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/products/memberships',
                'contentClass' => 'memberships-page-content',
            ]);
        }

        return view('products.memberships');
    }

    public function membershipsInfo(): View
    {
        $page = Page::where('slug', 'products-memberships-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/products/memberships-info',
                'contentClass' => 'memberships-info-page-content',
            ]);
        }

        return view('products.memberships-info');
    }

    public function rentalsInfo(): View
    {
        $page = Page::where('slug', 'products-rentals-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/products/rentals-info',
                'contentClass' => 'rentals-info-page-content',
            ]);
        }

        return view('products.rentals-info');
    }

    public function ticketsInfo(): View
    {
        $page = Page::where('slug', 'products-tickets-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('marketing.editable-page', [
                'page' => $page,
                'seoPath' => '/products/tickets-info',
                'contentClass' => 'tickets-info-page-content',
            ]);
        }

        return view('products.tickets-info');
    }
}
