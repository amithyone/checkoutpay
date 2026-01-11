<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        
        // For now, team management is a placeholder
        // In the future, you can add team members table
        return view('business.team.index', compact('business'));
    }
}
