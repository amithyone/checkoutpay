<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(): View
    {
        return view('payout.index');
    }
}
