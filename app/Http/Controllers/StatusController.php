<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('status');
        
        if ($page) {
            return view('status.index-editable', compact('page'));
        }

        return view('status.index');
    }
}
