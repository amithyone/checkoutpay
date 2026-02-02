<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(): View
    {
        $page = Page::getBySlug('payout');
        
        if ($page) {
            return view('payout.index-editable', compact('page'));
        }

        return view('payout.index');
    }
}
