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
}
