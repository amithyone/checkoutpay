<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ApiDocumentationController extends Controller
{
    /**
     * Display API documentation
     */
    public function index()
    {
        $business = auth('business')->user();
        
        // Read the API documentation markdown file
        $docPath = base_path('API_DOCUMENTATION.md');
        $documentation = File::exists($docPath) ? File::get($docPath) : 'API Documentation not available.';
        
        return view('business.api-documentation.index', compact('business', 'documentation'));
    }
}
