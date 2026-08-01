<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessDisbursementBatch;
use Illuminate\View\View;

class BusinessPayrollAdminController extends Controller
{
    public function index(): View
    {
        $batches = BusinessDisbursementBatch::query()
            ->with(['business', 'items'])
            ->latest('id')
            ->paginate(30);

        return view('admin.business-payroll.index', compact('batches'));
    }
}
