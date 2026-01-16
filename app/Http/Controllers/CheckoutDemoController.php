<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CheckoutDemoController extends Controller
{
    public function index(): View
    {
        return view('checkout-demo.index');
    }
}
