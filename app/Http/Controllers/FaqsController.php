<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FaqsController extends Controller
{
    public function index(): View
    {
        $page = \App\Models\Page::getBySlug('faqs');
        
        if ($page) {
            return view('faqs.index-editable', compact('page'));
        }

        return view('faqs.index', ['page' => new \App\Models\Page(['title' => 'FAQs', 'content' => ''])]);
    }
}
