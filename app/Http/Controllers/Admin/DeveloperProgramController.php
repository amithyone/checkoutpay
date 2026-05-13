<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeveloperProgramApplication;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DeveloperProgramController extends Controller
{
    public function index(): View
    {
        $applications = DeveloperProgramApplication::query()
            ->orderByDesc('created_at')
            ->paginate(30);

        $globalFeeSharePercent = Setting::get('developer_program_fee_share_percent');
        $feeShareBaseDescription = Setting::get('developer_program_fee_share_base_description')
            ?: 'CheckoutPay’s transaction fee revenue on qualifying attributed volume';

        return view('admin.developer-program.index', compact(
            'applications',
            'globalFeeSharePercent',
            'feeShareBaseDescription'
        ));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'developer_program_fee_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'developer_program_fee_share_base_description' => ['nullable', 'string', 'max:500'],
        ]);

        $pct = $validated['developer_program_fee_share_percent'] ?? null;
        if ($pct === null || $pct === '') {
            Setting::where('key', 'developer_program_fee_share_percent')->delete();
            Cache::forget('setting_developer_program_fee_share_percent');
        } else {
            Setting::set(
                'developer_program_fee_share_percent',
                (float) $pct,
                'float',
                'developer_program',
                'Default % of platform transaction fee revenue shared with approved developer partners (public program page).'
            );
        }

        $desc = isset($validated['developer_program_fee_share_base_description'])
            ? trim((string) $validated['developer_program_fee_share_base_description'])
            : '';
        if ($desc === '') {
            Setting::where('key', 'developer_program_fee_share_base_description')->delete();
            Cache::forget('setting_developer_program_fee_share_base_description');
        } else {
            Setting::set(
                'developer_program_fee_share_base_description',
                $desc,
                'string',
                'developer_program',
                'Short phrase for what the published percentage applies to (public Developer Program page).'
            );
        }

        return redirect()
            ->route('admin.developer-program.index')
            ->with('success', 'Developer program fee-share settings saved.');
    }

    public function updateApplication(Request $request, DeveloperProgramApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'partner_fee_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $application->status = $validated['status'];
        $application->partner_fee_share_percent = ($validated['partner_fee_share_percent'] ?? '') === ''
            ? null
            : (float) $validated['partner_fee_share_percent'];
        $application->admin_notes = $validated['admin_notes'] ?? null;

        if ($validated['status'] === 'approved' && $application->approved_at === null) {
            $application->approved_at = now();
        }
        if ($validated['status'] !== 'approved') {
            $application->approved_at = null;
        }

        $application->save();

        return redirect()
            ->route('admin.developer-program.index')
            ->with('success', 'Application updated.');
    }
}
