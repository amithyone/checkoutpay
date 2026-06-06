<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View|RedirectResponse
    {
        $page = Page::getBySlug($slug);

        if (! $page) {
            abort(404);
        }

        $canonicalPath = Seo::publicPathForPageSlug($slug);
        if ($canonicalPath !== '/page/'.$slug) {
            return redirect($canonicalPath, 301);
        }

        return view('page', compact('page'));
    }
}
