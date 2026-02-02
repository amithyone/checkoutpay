<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class ProductsController extends Controller
{
    public function index(): View
    {
        return view('products.index');
    }

    public function invoices(): View
    {
        // Check if editable page exists in admin
        $page = Page::where('slug', 'products-invoices')
            ->where('is_published', true)
            ->first();

        if ($page) {
            // Use editable page content
            return view('products.invoices-editable', compact('page'));
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
            // Use editable page content
            return view('products.memberships-editable', compact('page'));
        }

        // Fallback to static view - redirect to public memberships page
        return redirect()->route('memberships.index');
    }

    public function membershipsInfo(): View
    {
        $page = Page::where('slug', 'products-memberships-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('products.memberships-info-editable', compact('page'));
        }

        return view('products.memberships-info');
    }

    public function rentalsInfo(): View
    {
        $page = Page::where('slug', 'products-rentals-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('products.rentals-info-editable', compact('page'));
        }

        return view('products.rentals-info');
    }

    public function ticketsInfo(): View
    {
        $page = Page::where('slug', 'products-tickets-info')
            ->where('is_published', true)
            ->first();

        if ($page) {
            return view('products.tickets-info-editable', compact('page'));
        }

        return view('products.tickets-info');
    }
}
