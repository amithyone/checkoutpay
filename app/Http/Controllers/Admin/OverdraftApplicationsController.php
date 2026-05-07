<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\View\View;

class OverdraftApplicationsController extends Controller
{
    public function index(): View
    {
        $applications = Business::query()
            ->where('overdraft_status', 'pending')
            ->orderByDesc('overdraft_requested_at')
            ->paginate(25);

        return view('admin.overdraft-applications.index', compact('applications'));
    }
}
