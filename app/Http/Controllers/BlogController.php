<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('blog');
        
        if ($page) {
            return view('blog.index-editable', compact('page'));
        }

        return view('blog.index');
    }
}
