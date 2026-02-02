<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class CollectionsController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('collections');
        
        if ($page) {
            return view('collections.index-editable', compact('page'));
        }

        return view('collections.index');
    }
}
