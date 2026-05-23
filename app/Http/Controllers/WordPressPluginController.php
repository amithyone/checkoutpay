<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WordPressPluginController extends Controller
{
    public function index(): View
    {
        return view('wordpress-plugin.index');
    }
}
