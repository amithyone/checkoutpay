<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ProductsController extends Controller
{
    public function index(): View
    {
        return view('products.index');
    }
}
