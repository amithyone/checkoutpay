<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessDisbursementBatch;
use App\Models\BusinessSalarySchedule;
use App\Services\Business\BusinessPayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private BusinessPayrollService $payroll,
    ) {}

    public function index(): View
    {
        $business = Auth::guard('business')->user();
        $batches = BusinessDisbursementBatch::query()
            ->where('business_id', $business->id)
            ->latest('id')
            ->paginate(20);
        $schedules = BusinessSalarySchedule::query()
            ->where('business_id', $business->id)
            ->latest('id')
            ->get();

        // Process any due salary items when the business opens payroll UI.
        app(\App\Services\Business\BusinessPayrollDueRunner::class)->tick(force: false, minIntervalSeconds: 30);

        return view('business.team.payroll.index', compact('business', 'batches', 'schedules'));
    }

    public function bulkForm(): View
    {
        $business = Auth::guard('business')->user();
        $employees = $business->employees()->where('is_active', true)->orderBy('name')->get();
        $businessBalance = $this->payroll->availableBusinessBalance($business);

        return view('business.team.payroll.bulk', compact('business', 'employees', 'businessBalance'));
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        $validated = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount_mode' => ['nullable', 'in:cycle,monthly'],
        ]);
        $employeeIds = $validated['employee_ids'] ?? [];
        if (! is_array($employeeIds)) {
            $employeeIds = [];
        }

        $result = $this->payroll->createBulkBatch(
            $business,
            $employeeIds,
            $validated['notes'] ?? null,
            (string) ($validated['amount_mode'] ?? 'cycle'),
        );
        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Could not create payroll batch.');
        }

        /** @var BusinessDisbursementBatch $batch */
        $batch = $result['batch'];
        $this->payroll->processBatch($batch);

        return redirect()->route('business.team.payroll.index')
            ->with('success', 'Bulk payroll processed.');
    }

    public function scheduleForm(): View
    {
        $business = Auth::guard('business')->user();
        $employees = $business->employees()->where('is_active', true)->orderBy('name')->get();

        return view('business.team.payroll.schedule', compact('business', 'employees'));
    }

    public function scheduleStore(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cadence' => 'required|in:daily,weekly,biweekly,monthly',
            'total_monthly_amount_ngn' => 'nullable|numeric|min:0',
            'installment_count' => 'required|integer|min:1|max:31',
            'start_date' => 'required|date',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer',
        ]);

        $result = $this->payroll->createSchedule($business, $validated);
        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Could not create schedule.');
        }

        return redirect()->route('business.team.payroll.index')->with('success', 'Salary schedule created.');
    }
}
