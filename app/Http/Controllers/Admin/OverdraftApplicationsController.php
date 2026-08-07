<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class OverdraftApplicationsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.credit-facility-applications.index');
    }
}
