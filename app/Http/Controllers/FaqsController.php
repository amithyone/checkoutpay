<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FaqsController extends Controller
{
    public function index(): View
    {
        $page = \App\Models\Page::getBySlug('faqs') ?? new \App\Models\Page(['title' => 'FAQs', 'content' => '']);
        return view('faqs.index', compact('page'));
    }
}
