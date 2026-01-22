<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocsController extends Controller
{
    /**
     * Display public API documentation
     */
    public function index()
    {
        return view('api-docs.index');
    }
}
