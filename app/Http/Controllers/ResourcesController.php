<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ResourcesController extends Controller
{
    public function index(): View
    {
        return view('resources.index');
    }
}
