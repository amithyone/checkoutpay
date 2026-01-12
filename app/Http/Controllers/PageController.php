<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::getBySlug($slug);

        if (!$page) {
            abort(404);
        }

        return view('page', compact('page'));
    }
}
