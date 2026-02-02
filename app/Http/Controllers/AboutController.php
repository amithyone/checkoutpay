<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AboutController extends Controller
{
    public function index(): View
    {
        $page = \App\Models\Page::getBySlug('about-us');
        
        if ($page) {
            return view('about.index-editable', compact('page'));
        }

        return view('about.index', ['page' => new \App\Models\Page(['title' => 'About Us', 'content' => ''])]);
    }
}
