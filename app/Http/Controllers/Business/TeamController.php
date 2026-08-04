<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessEmployee;
use App\Services\BankLogoService;
use App\Services\Business\BusinessPayrollService;
use App\Services\NigerianBankCodeNormalizer;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        private BusinessPayrollService $payroll,
        private BankLogoService $bankLogos,
    ) {}

    public function index(): View
    {
        $business = Auth::guard('business')->user();
        $employees = BusinessEmployee::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get();
        $linkedWallet = $this->payroll->linkedWallet($business);
        $banks = $this->bankLogos->listForApi();
        $businessBalance = $this->payroll->availableBusinessBalance($business);

        return view('business.team.index', compact('business', 'employees', 'linkedWallet', 'banks', 'businessBalance'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        $validated = $this->validateEmployee($request);

        BusinessEmployee::query()->create(array_merge($validated, [
            'business_id' => $business->id,
        ]));

        return redirect()->route('business.team.index')->with('success', 'Employee added.');
    }

    public function update(Request $request, BusinessEmployee $employee): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        abort_unless((int) $employee->business_id === (int) $business->id, 404);

        $employee->update($this->validateEmployee($request));

        return redirect()->route('business.team.index')->with('success', 'Employee updated.');
    }

    public function destroy(BusinessEmployee $employee): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        abort_unless((int) $employee->business_id === (int) $business->id, 404);
        $employee->delete();

        return redirect()->route('business.team.index')->with('success', 'Employee removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'payment_method' => ['required', Rule::in([BusinessEmployee::METHOD_BANK, BusinessEmployee::METHOD_WALLET])],
            'phone_e164' => 'nullable|string|max:20',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
            'monthly_salary_ngn' => 'required|numeric|min:0',
            'pay_frequency' => ['required', Rule::in([
                BusinessEmployee::FREQUENCY_MONTHLY,
                BusinessEmployee::FREQUENCY_BIWEEKLY,
                BusinessEmployee::FREQUENCY_WEEKLY,
                BusinessEmployee::FREQUENCY_DAILY,
            ])],
            'pay_day_hint' => 'nullable|string|max:40',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['payment_method'] === BusinessEmployee::METHOD_WALLET) {
            $phone = PhoneNormalizer::canonicalAuthE164Digits((string) ($validated['phone_e164'] ?? ''));
            if ($phone === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone_e164' => 'Enter a valid wallet phone number.',
                ]);
            }
            $validated['phone_e164'] = $phone;
            $validated['bank_code'] = null;
            $validated['account_number'] = null;
            $validated['account_name'] = null;
        } else {
            if (trim((string) ($validated['bank_code'] ?? '')) === '' || trim((string) ($validated['account_number'] ?? '')) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'account_number' => 'Bank and account number are required for bank payments.',
                ]);
            }
            $validated['bank_code'] = NigerianBankCodeNormalizer::toNipTransferCode((string) $validated['bank_code']);
            $validated['phone_e164'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['pay_day_hint'] = isset($validated['pay_day_hint']) ? trim((string) $validated['pay_day_hint']) ?: null : null;

        return $validated;
    }
}
