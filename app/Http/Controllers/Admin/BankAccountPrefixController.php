<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccountPrefixRule;
use App\Services\BankAccountSuggestionService;
use App\Services\BankLogoService;
use App\Services\NigerianBankCodeNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankAccountPrefixController extends Controller
{
    public function __construct(
        private BankAccountSuggestionService $suggestions,
        private BankLogoService $bankLogos,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $query = BankAccountPrefixRule::query()
            ->with('createdBy:id,name')
            ->orderByDesc('prefix');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('prefix', 'like', $q.'%')
                    ->orWhere('bank_code', 'like', '%'.$q.'%')
                    ->orWhere('bank_name', 'like', '%'.$q.'%');
            });
        }

        $rules = $query->paginate(30)->withQueryString();
        $banks = $this->bankLogos->listForApi();

        $previewAccount = preg_replace('/\D+/', '', (string) $request->query('preview', '')) ?? '';
        $previewBanks = $previewAccount !== '' && strlen($previewAccount) >= 2
            ? $this->suggestions->suggest($previewAccount)
            : [];

        return view('admin.bank-account-prefixes.index', compact('rules', 'banks', 'q', 'previewAccount', 'previewBanks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        BankAccountPrefixRule::create([
            ...$validated,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return back()->with('success', 'Bank prefix suggestion added.');
    }

    public function update(Request $request, BankAccountPrefixRule $bankAccountPrefix): RedirectResponse
    {
        $bankAccountPrefix->update($this->validated($request, $bankAccountPrefix));

        return back()->with('success', 'Bank prefix suggestion updated.');
    }

    public function destroy(BankAccountPrefixRule $bankAccountPrefix): RedirectResponse
    {
        $bankAccountPrefix->delete();

        return back()->with('success', 'Bank prefix suggestion removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BankAccountPrefixRule $existing = null): array
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:10',
            'bank_code' => 'required|string|max:20',
            'bank_name' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $prefix = preg_replace('/\D+/', '', (string) $validated['prefix']) ?? '';
        if (strlen($prefix) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'prefix' => 'Prefix must be at least 2 digits.',
            ]);
        }

        $bankCode = NigerianBankCodeNormalizer::toNipTransferCode((string) $validated['bank_code']);
        $bankName = trim((string) ($validated['bank_name'] ?? ''));

        if ($bankName === '') {
            $bank = Bank::query()->where('code', $bankCode)->first(['name']);
            $bankName = $bank ? (string) $bank->name : '';
        }

        $uniqueRule = 'unique:bank_account_prefix_rules,prefix';
        if ($existing !== null) {
            $uniqueRule .= ','.$existing->id;
        }

        $request->merge(['prefix' => $prefix]);
        $request->validate(['prefix' => $uniqueRule]);

        return [
            'prefix' => $prefix,
            'bank_code' => $bankCode,
            'bank_name' => $bankName !== '' ? $bankName : null,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'is_active' => $existing === null
                ? $request->boolean('is_active', true)
                : $request->boolean('is_active'),
        ];
    }
}
