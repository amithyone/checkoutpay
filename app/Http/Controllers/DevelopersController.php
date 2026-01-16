<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DevelopersController extends Controller
{
    public function index(): View
    {
        return view('developers.index');
    }
}
