<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessAccountOnboardingService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerBusinessAccountOnboardingController extends Controller
{
    public function __construct(
        private ConsumerBusinessAccountOnboardingService $onboarding,
        private ConsumerWalletPinVerifier $pinVerifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh(['linkedBusiness']);

        return response()->json([
            'success' => true,
            'data' => $this->onboarding->index($wallet),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->onboarding->isLive()) {
            return response()->json([
                'success' => false,
                'message' => (string) config(
                    'consumer_wallet.business_account_onboarding.coming_soon_message',
                    'Business account onboarding coming soon.'
                ),
            ], 403);
        }

        $validated = $request->validate([
            'account_plan' => 'required|string|in:payments_only,payments_and_web',
            'service_categories' => 'nullable|array',
            'service_categories.*' => 'string|in:payments,rentals,memberships,tickets,charity,invoices',
            'business_name' => 'required|string|min:3|max:200',
            'email' => 'required|email|max:160',
            'phone' => 'nullable|string|max:32',
            'address' => 'required|string|max:1000',
            'website_url' => 'nullable|url|max:500',
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'cac_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'cac_number' => 'nullable|string|max:64',
            'tin' => 'nullable|string|max:64',
            'registered_address' => 'nullable|string|max:1000',
            'operating_address' => 'nullable|string|max:1000',
            'actual_activity' => 'nullable|string|max:2000',
            'sector' => 'nullable|string|max:128',
            'source_of_funds' => 'nullable|string|max:255',
            'source_of_wealth' => 'nullable|string|max:255',
            'expected_profile' => 'nullable|array',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $validated['pin'])) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 422);
        }

        $result = $this->onboarding->submit(
            $wallet,
            $validated,
            $request->file('cac_document'),
            $request->ip(),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['http_status'] ?? ($result['ok'] ? 200 : 422));
    }

    public function storeDocument(Request $request, \App\Services\Consumer\BusinessKybComplianceService $kyb): JsonResponse
    {
        $request->validate([
            'kind' => 'required|string|in:cac_certificate,memart,cac_status_report,licence,address_evidence',
            'file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $out = $kyb->storeDocument($application, (string) $request->input('kind'), $request->file('file'));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => $this->onboarding->serializeApplication($application->fresh(['kycParties'])),
        ], $out['ok'] ? 200 : 422);
    }

    public function storeParty(Request $request, \App\Services\Consumer\BusinessKybComplianceService $kyb): JsonResponse
    {
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $out = $kyb->upsertParty($application, $request->all());

        return $this->partyResponse($out, $application);
    }

    public function updateParty(Request $request, int $id, \App\Services\Consumer\BusinessKybComplianceService $kyb): JsonResponse
    {
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $party = $application->kycParties()->where('id', $id)->first();
        if (! $party) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
        }
        $out = $kyb->upsertParty($application, $request->all(), $party);

        return $this->partyResponse($out, $application);
    }

    public function destroyParty(Request $request, int $id): JsonResponse
    {
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $party = $application->kycParties()->where('id', $id)->first();
        if (! $party) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
        }
        $party->delete();
        $application->load('kycParties');
        app(\App\Services\Consumer\BusinessKybComplianceService::class)->refreshStatus($application);
        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Party removed.',
            'data' => $this->onboarding->serializeApplication($application->fresh(['kycParties'])),
        ]);
    }

    public function storePartyDocument(Request $request, int $id, \App\Services\Consumer\BusinessKybComplianceService $kyb): JsonResponse
    {
        $request->validate([
            'kind' => 'required|string|in:id,authority',
            'file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $party = $application->kycParties()->where('id', $id)->first();
        if (! $party) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
        }
        $out = $kyb->storePartyDocument($party, (string) $request->input('kind'), $request->file('file'));
        $application->load('kycParties');
        $kyb->refreshStatus($application);
        $application->save();

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $this->onboarding->serializeApplication($application->fresh(['kycParties'])),
        ]);
    }

    public function submitForReview(Request $request, \App\Services\Consumer\BusinessKybComplianceService $kyb): JsonResponse
    {
        $application = $this->onboarding->currentApplication($this->walletFor($request));
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'No application in progress.', 'error_code' => 'kyb_incomplete'], 422);
        }
        $out = $kyb->submitForReview($application);

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => $this->onboarding->serializeApplication($application->fresh(['kycParties'])),
        ], $out['ok'] ? 200 : (int) ($out['http_status'] ?? 422));
    }

    /**
     * @param  array{ok: bool, message: string, error_code?: string, http_status?: int, party?: \App\Models\BusinessKycParty}  $out
     */
    private function partyResponse(array $out, \App\Models\BusinessAccountApplication $application): JsonResponse
    {
        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => [
                'application' => $this->onboarding->serializeApplication($application->fresh(['kycParties'])),
                'party' => isset($out['party']) ? app(\App\Services\Consumer\BusinessKybComplianceService::class)->serializeParty($out['party']) : null,
            ],
        ], $out['ok'] ? 200 : (int) ($out['http_status'] ?? 422));
    }

    public function setPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|max:128',
            'password_confirmation' => 'required|string|same:password',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->onboarding->setPassword(
            $wallet,
            (string) $validated['password'],
            (string) $validated['password_confirmation'],
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['http_status'] ?? ($result['ok'] ? 200 : 422));
    }

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }
        $user->loadMissing('wallet');
        $w = $user->wallet;
        if (! $w) {
            abort(403, 'Wallet not linked.');
        }

        return $w;
    }
}
