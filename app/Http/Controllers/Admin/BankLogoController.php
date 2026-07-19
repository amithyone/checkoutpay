<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\BankLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankLogoController extends Controller
{
    public function __construct(
        private BankLogoService $logos,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        $query = Bank::query()->orderBy('name');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }
        if ($status === 'mapped') {
            $query->whereNotNull('logo_path')->where('logo_path', '!=', '');
        } elseif ($status === 'unmapped') {
            $query->where(function ($w) {
                $w->whereNull('logo_path')->orWhere('logo_path', '');
            });
        }

        $banks = $query->paginate(40)->withQueryString();
        $library = $this->logos->libraryFilenames();
        $suggestions = [];
        foreach ($banks as $bank) {
            if (! $bank->hasLogo()) {
                $suggestions[$bank->id] = $this->logos->suggestLibraryFilename($bank);
            }
        }

        $stats = [
            'total' => Bank::query()->count(),
            'mapped' => Bank::query()->whereNotNull('logo_path')->where('logo_path', '!=', '')->count(),
        ];
        $stats['unmapped'] = max(0, $stats['total'] - $stats['mapped']);

        return view('admin.bank-logos.index', compact('banks', 'library', 'suggestions', 'stats', 'q', 'status'));
    }

    public function upload(Request $request, Bank $bank): RedirectResponse
    {
        $request->validate([
            'logo' => 'required|file|mimes:svg,png,jpg,jpeg,webp|max:512',
        ]);

        $this->logos->storeUpload($bank, $request->file('logo'));

        return back()->with('success', "Logo uploaded for {$bank->name}.");
    }

    public function assign(Request $request, Bank $bank): RedirectResponse
    {
        $validated = $request->validate([
            'library_file' => 'required|string|max:255',
        ]);

        $this->logos->assignLibraryLogo($bank, $validated['library_file'], 'nbl');

        return back()->with('success', "Library logo assigned to {$bank->name}.");
    }

    public function clear(Bank $bank): RedirectResponse
    {
        $this->logos->clearLogo($bank);

        return back()->with('success', "Logo cleared for {$bank->name}.");
    }

    public function autoMap(Request $request): RedirectResponse
    {
        $force = $request->boolean('force');
        $result = $this->logos->autoMap(force: $force);

        return back()->with(
            'success',
            "Auto-map done: {$result['mapped']} mapped, {$result['skipped']} skipped, {$result['missing_library']} missing from library."
        );
    }
}
