<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AboutController extends Controller
{
    public function index(): View
    {
        $page = \App\Models\Page::getBySlug('about-us') ?? new \App\Models\Page(['title' => 'About Us', 'content' => '']);
        return view('about.index', compact('page'));
    }
}
