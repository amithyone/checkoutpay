<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RenterKycController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', Renter::KYC_ID_STATUS_PENDING);

        $renters = Renter::query()
            ->whereNotNull('kyc_id_front_path')
            ->whereNotNull('kyc_id_back_path')
            ->when($status, fn ($q) => $q->where('kyc_id_status', $status))
            ->orderByDesc('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.renters-kyc.index', [
            'renters' => $renters,
            'status' => $status,
        ]);
    }

    public function approve(Renter $renter)
    {
        $renter->update([
            'kyc_id_status' => Renter::KYC_ID_STATUS_APPROVED,
            'kyc_id_reviewed_at' => now(),
            'kyc_id_reviewed_by' => Auth::id(),
            'kyc_id_rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Renter ID approved.');
    }

    public function reject(Request $request, Renter $renter)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $renter->update([
            'kyc_id_status' => Renter::KYC_ID_STATUS_REJECTED,
            'kyc_id_reviewed_at' => now(),
            'kyc_id_reviewed_by' => Auth::id(),
            'kyc_id_rejection_reason' => $validated['reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Renter ID rejected.');
    }
}

