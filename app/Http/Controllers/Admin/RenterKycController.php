<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use App\Services\MevonRubiesVirtualAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenterKycController extends Controller
{
    public function __construct(
        protected MevonRubiesVirtualAccountService $mevonRubiesVirtualAccountService
    ) {}

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

        // Provision a persistent Rubies VA once KYC is approved.
        // This keeps rentals users from creating VA on the frontend.
        if (! $renter->rubies_account_number) {
            try {
                $va = $this->mevonRubiesVirtualAccountService->createRenterAccount($renter);
                $renter->update([
                    'rubies_account_number' => $va['account_number'] ?? null,
                    'rubies_account_name' => $va['account_name'] ?? null,
                    'rubies_bank_name' => $va['bank_name'] ?? null,
                    'rubies_bank_code' => $va['bank_code'] ?? null,
                    'rubies_reference' => $va['reference'] ?? null,
                    'rubies_account_created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Rubies account auto-creation failed on renter KYC approval', [
                    'renter_id' => $renter->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->with(
                    'success',
                    'Renter ID approved. Rubies VA was not created automatically (invalid details, or an OTP is required on the renter’s phone — they can complete Tier 2 on WhatsApp from that number).'
                );
            }
        }

        return redirect()->back()->with('success', 'Renter ID approved and Rubies account created.');
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
