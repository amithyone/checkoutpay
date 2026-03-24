<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RenterKycController extends Controller
{
    protected function kycPathExists(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk('public')->exists($path) || Storage::disk('local')->exists($path);
    }

    public function index(Request $request)
    {
        $status = $request->query('status', Renter::KYC_ID_STATUS_PENDING);
        $singleRenterId = $request->filled('renter') ? (int) $request->query('renter') : null;

        $renters = Renter::query()
            ->when($singleRenterId, function ($q) use ($singleRenterId) {
                $q->where('id', $singleRenterId);
            }, function ($q) {
                // Include both legacy single-file uploads and new front/back uploads
                $q->where(function ($inner) {
                    $inner->whereNotNull('kyc_id_card_path')
                        ->orWhereNotNull('kyc_id_front_path')
                        ->orWhereNotNull('kyc_id_back_path');
                });
            })
            ->when(! $singleRenterId && $status, function ($q) use ($status) {
                if ($status === Renter::KYC_ID_STATUS_PENDING) {
                    // Treat null as pending for older rows
                    $q->where(function ($sq) {
                        $sq->whereNull('kyc_id_status')->orWhere('kyc_id_status', Renter::KYC_ID_STATUS_PENDING);
                    });

                    return;
                }
                $q->where('kyc_id_status', $status);
            })
            ->orderByDesc('updated_at')
            ->paginate($singleRenterId ? 1 : 30)
            ->withQueryString();

        // Attach doc existence flags so UI can avoid broken previews.
        $renters->getCollection()->transform(function (Renter $renter) {
            $renter->kyc_id_front_exists = $this->kycPathExists($renter->kyc_id_front_path);
            $renter->kyc_id_back_exists = $this->kycPathExists($renter->kyc_id_back_path);
            $renter->kyc_id_card_exists = $this->kycPathExists($renter->kyc_id_card_path);

            return $renter;
        });

        return view('admin.renters-kyc.index', [
            'renters' => $renters,
            'status' => $status,
            'singleRenterId' => $singleRenterId,
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

    public function toggleActive(Renter $renter)
    {
        $newState = ! (bool) $renter->is_active;
        $renter->update(['is_active' => $newState]);

        return redirect()->back()->with(
            'success',
            $newState ? 'Renter account enabled.' : 'Renter account disabled.'
        );
    }

    public function document(Renter $renter, string $type)
    {
        $path = match ($type) {
            'front' => $renter->kyc_id_front_path,
            'back' => $renter->kyc_id_back_path,
            'card' => $renter->kyc_id_card_path,
            default => null,
        };

        if (! $path) {
            abort(404, 'Document not found');
        }

        $disk = Storage::disk('public')->exists($path)
            ? Storage::disk('public')
            : (Storage::disk('local')->exists($path) ? Storage::disk('local') : null);

        if (! $disk) {
            abort(404, 'Document not found');
        }

        $fullPath = $disk->path($path);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
