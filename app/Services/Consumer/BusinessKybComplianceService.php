<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\BusinessAccountApplication;
use App\Models\BusinessKycParty;
use App\Models\BusinessVerification;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\MevonIdentityVerificationService;
use App\Support\WhatsappWalletKycInputGuard;
use Illuminate\Http\UploadedFile;

final class BusinessKybComplianceService
{
    public const UBO_THRESHOLD = 5.0;

    public const STANDARD_DAILY_LIMIT = 10_000_000.0;

    public const DOC_KINDS = [
        'cac_certificate' => 'cac_document_path',
        'memart' => 'memart_path',
        'cac_status_report' => 'cac_status_report_path',
        'licence' => 'licence_path',
        'address_evidence' => 'address_evidence_path',
    ];

    public function __construct(
        private MevonIdentityVerificationService $identity,
        private WalletKycComplianceService $personalKyc,
    ) {}

    public function failClosedEnabled(): bool
    {
        return $this->personalKyc->failClosedEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function kybPayload(?BusinessAccountApplication $application): array
    {
        if ($application) {
            $application->loadMissing('kycParties.children');
        }
        if (! $application) {
            return [
                'kyb_status' => 'incomplete',
                'missing_requirements' => ['application'],
                'daily_limit' => self::STANDARD_DAILY_LIMIT,
                'edd_required' => false,
                'parties' => [],
            ];
        }

        $missing = $this->missingRequirements($application);
        $status = (string) ($application->kyb_status ?: 'incomplete');
        if ($missing === [] && $status === 'incomplete') {
            $status = 'incomplete';
        }

        return [
            'kyb_status' => $status,
            'missing_requirements' => $missing,
            'daily_limit' => (float) ($application->daily_limit_ngn ?: self::STANDARD_DAILY_LIMIT),
            'edd_required' => (bool) $application->edd_required,
            'cac_status_confirmed' => $application->cac_status_confirmed_at !== null,
            'parties' => $application->kycParties->map(fn (BusinessKycParty $p) => $this->serializeParty($p))->values()->all(),
        ];
    }

    /**
     * @return list<string>
     */
    public function missingRequirements(BusinessAccountApplication $application): array
    {
        $application->loadMissing('kycParties.children');
        $missing = [];
        $cac = Business::normalizeCacRegistrationNumber((string) ($application->cac_number ?? ''));
        if ($cac === '') {
            $missing[] = 'cac_number';
        }
        if (trim((string) $application->cac_document_path) === '') {
            $missing[] = 'cac_certificate';
        }
        $name = trim((string) $application->business_name);
        if ($name === '' || strcasecmp($name, $cac) === 0) {
            $missing[] = 'business_name';
        }
        if (trim((string) ($application->registered_address ?: $application->address)) === '') {
            $missing[] = 'registered_address';
        }
        if (trim((string) $application->actual_activity) === '') {
            $missing[] = 'actual_activity';
        }
        if (trim((string) $application->sector) === '') {
            $missing[] = 'sector';
        }
        if (trim((string) $application->source_of_funds) === '') {
            $missing[] = 'source_of_funds';
        }

        $parties = $application->kycParties;
        if ($parties->where('role', BusinessKycParty::ROLE_DIRECTOR)->isEmpty()) {
            $missing[] = 'director';
        }
        if ($parties->where('role', BusinessKycParty::ROLE_UBO)->isEmpty()) {
            $missing[] = 'ubo';
        }
        if ($parties->where('role', BusinessKycParty::ROLE_SIGNATORY)->isEmpty()) {
            $missing[] = 'signatory';
        }

        $naturalUbos = $parties->filter(
            fn (BusinessKycParty $p) => $p->role === BusinessKycParty::ROLE_UBO
                && $p->person_type === BusinessKycParty::PERSON_NATURAL
        );
        $pct = (float) $naturalUbos->sum(fn (BusinessKycParty $p) => (float) $p->ownership_percent);
        if ($naturalUbos->isEmpty() || ($pct + 0.0001 < self::UBO_THRESHOLD && $naturalUbos->where('ownership_percent', '>', 0)->isEmpty() === false && $pct < self::UBO_THRESHOLD)) {
            // Always require at least one natural UBO; if percents are given they must cover 5%+
            if ($naturalUbos->isEmpty()) {
                $missing[] = 'ubo';
            } elseif ($naturalUbos->contains(fn (BusinessKycParty $p) => $p->ownership_percent !== null)
                && $pct + 0.0001 < self::UBO_THRESHOLD) {
                $missing[] = 'ubo_threshold';
            }
        }

        foreach ($parties as $party) {
            if ($party->person_type === BusinessKycParty::PERSON_CORPORATE && $party->children->isEmpty() && $party->role !== BusinessKycParty::ROLE_DIRECTOR) {
                $missing[] = 'look_through_'.$party->id;
            }
            if ($party->isNigerianNatural() && $party->identity_verified_at === null) {
                $missing[] = 'party_identity_'.$party->id;
            }
            if ($party->role === BusinessKycParty::ROLE_SIGNATORY && trim((string) $party->authority_document_path) === '') {
                $missing[] = 'signatory_authority';
            }
        }

        if ($application->cac_status_confirmed_at === null) {
            $missing[] = 'cac_status_confirmation';
        }

        return array_values(array_unique($missing));
    }

    public function isReadyForReview(BusinessAccountApplication $application): bool
    {
        $missing = $this->missingRequirements($application);

        return ! in_array('ubo', $missing, true)
            && ! in_array('ubo_threshold', $missing, true)
            && ! in_array('cac_certificate', $missing, true)
            && ! in_array('director', $missing, true)
            && ! in_array('cac_number', $missing, true)
            && collect($missing)->filter(fn ($m) => str_starts_with((string) $m, 'party_identity_'))->isEmpty()
            && collect($missing)->filter(fn ($m) => str_starts_with((string) $m, 'look_through_'))->isEmpty();
    }

    public function isReadyToApprove(BusinessAccountApplication $application): bool
    {
        return $this->missingRequirements($application) === [];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function applyCoreFields(BusinessAccountApplication $application, array $input): void
    {
        $updates = [];
        foreach ([
            'cac_number', 'tin', 'registered_address', 'operating_address',
            'actual_activity', 'sector', 'source_of_funds', 'source_of_wealth',
        ] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $updates[$key] = is_string($input[$key]) ? trim((string) $input[$key]) : $input[$key];
            }
        }
        if (isset($input['expected_profile']) && is_array($input['expected_profile'])) {
            $updates['expected_profile'] = $input['expected_profile'];
        }
        if (isset($updates['cac_number'])) {
            $updates['cac_number'] = Business::normalizeCacRegistrationNumber((string) $updates['cac_number']);
        }
        if ($updates !== []) {
            $application->fill($updates);
        }
        $application->kyb_status = $application->kyb_status ?: 'incomplete';
        $application->daily_limit_ngn = $application->daily_limit_ngn ?: self::STANDARD_DAILY_LIMIT;
        $this->refreshStatus($application);
        $application->save();
    }

    /**
     * @return array{ok: bool, message: string, error_code?: string}
     */
    public function storeDocument(BusinessAccountApplication $application, string $kind, UploadedFile $file): array
    {
        if (! isset(self::DOC_KINDS[$kind])) {
            return ['ok' => false, 'message' => 'Unknown document kind.', 'error_code' => 'kyb_incomplete'];
        }
        $column = self::DOC_KINDS[$kind];
        $path = $file->store('business-kyb/'.$application->id.'/'.$kind, 'local');
        $application->{$column} = $path;
        $this->refreshStatus($application);
        $application->save();

        return ['ok' => true, 'message' => 'Document uploaded.'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, error_code?: string, party?: BusinessKycParty, http_status?: int}
     */
    public function upsertParty(BusinessAccountApplication $application, array $input, ?BusinessKycParty $existing = null): array
    {
        $role = (string) ($input['role'] ?? '');
        $personType = (string) ($input['person_type'] ?? BusinessKycParty::PERSON_NATURAL);
        $legalName = trim((string) ($input['legal_name'] ?? ''));
        if (! in_array($role, BusinessKycParty::ROLES, true)) {
            return ['ok' => false, 'message' => 'Invalid party role.', 'error_code' => 'kyb_incomplete'];
        }
        if (! in_array($personType, [BusinessKycParty::PERSON_NATURAL, BusinessKycParty::PERSON_CORPORATE], true)) {
            return ['ok' => false, 'message' => 'Invalid person type.', 'error_code' => 'kyb_incomplete'];
        }
        if (mb_strlen($legalName) < 2) {
            return ['ok' => false, 'message' => 'Legal name is required.', 'error_code' => 'kyb_incomplete'];
        }

        $party = $existing ?? new BusinessKycParty([
            'business_account_application_id' => $application->id,
        ]);
        $party->role = $role;
        $party->person_type = $personType;
        $party->legal_name = $legalName;
        $party->dob = ! empty($input['dob']) ? $input['dob'] : $party->dob;
        $party->nationality = isset($input['nationality']) ? strtoupper(trim((string) $input['nationality'])) : $party->nationality;
        $party->email = isset($input['email']) ? strtolower(trim((string) $input['email'])) : $party->email;
        $party->phone = $input['phone'] ?? $party->phone;
        $party->ownership_percent = array_key_exists('ownership_percent', $input) ? $input['ownership_percent'] : $party->ownership_percent;
        $party->is_nominee = (bool) ($input['is_nominee'] ?? $party->is_nominee);
        $party->parent_party_id = $input['parent_party_id'] ?? $party->parent_party_id;

        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? $party->bvn ?? '')) ?? '';
        $nin = preg_replace('/\D+/', '', (string) ($input['nin'] ?? $party->nin ?? '')) ?? '';
        $party->bvn = strlen($bvn) === 11 ? $bvn : $party->bvn;
        $party->nin = strlen($nin) === 11 ? $nin : $party->nin;

        if ($personType === BusinessKycParty::PERSON_NATURAL && $party->isNigerianNatural()) {
            $dob = $party->dob?->format('Y-m-d') ?? '';
            $names = preg_split('/\s+/', $legalName) ?: [];
            $fname = (string) array_shift($names);
            $lname = $names !== [] ? implode(' ', $names) : $fname;
            if (strlen((string) $party->bvn) !== 11 && strlen((string) $party->nin) !== 11) {
                return ['ok' => false, 'message' => 'BVN or NIN is required for Nigerian persons.', 'error_code' => 'kyb_incomplete'];
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                return ['ok' => false, 'message' => 'Date of birth is required for Nigerian persons.', 'error_code' => 'kyb_incomplete'];
            }
            try {
                $verified = $this->identity->verifyPersonal(
                    $fname,
                    $lname,
                    $dob,
                    strlen((string) $party->bvn) === 11 ? $party->bvn : null,
                    strlen((string) $party->bvn) === 11 ? null : $party->nin,
                );
            } catch (\Throwable $e) {
                return ['ok' => false, 'message' => 'Identity verification unavailable.', 'error_code' => 'identity_provider_unavailable'];
            }
            if (! ($verified['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string) ($verified['message'] ?? 'Identity mismatch.'),
                    'error_code' => 'identity_mismatch',
                ];
            }
            $party->identity_verified_at = now();
            $party->mevon_reference = (string) ($verified['reference'] ?? '');
            $party->mevon_full_name = (string) ($verified['full_name'] ?? $legalName);
        }

        $party->save();
        $application->load('kycParties');
        $this->refreshStatus($application);
        $application->save();

        return ['ok' => true, 'message' => 'Party saved.', 'party' => $party];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function storePartyDocument(BusinessKycParty $party, string $kind, UploadedFile $file): array
    {
        $path = $file->store('business-kyb/parties/'.$party->id, 'local');
        if ($kind === 'authority') {
            $party->authority_document_path = $path;
        } else {
            $party->id_document_path = $path;
        }
        $party->save();

        return ['ok' => true, 'message' => 'Document uploaded.'];
    }

    /**
     * @return array{ok: bool, message: string, error_code?: string, http_status?: int}
     */
    public function submitForReview(BusinessAccountApplication $application): array
    {
        $application->load(['kycParties.children']);
        if ($application->kycParties->where('role', BusinessKycParty::ROLE_UBO)->filter(
            fn (BusinessKycParty $p) => $p->person_type === BusinessKycParty::PERSON_NATURAL
        )->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Beneficial owners must be identified as natural persons before an account can be created.',
                'error_code' => 'ubo_not_established',
                'http_status' => 422,
            ];
        }

        $cac = Business::normalizeCacRegistrationNumber((string) ($application->cac_number ?? ''));
        $name = trim((string) $application->business_name);
        if ($cac !== '' && $name !== '' && $this->nameConflictsWithCac($name, $cac)) {
            $application->kyb_status = 'review';
            $application->save();

            return [
                'ok' => false,
                'message' => 'Business name must match the CAC registered name.',
                'error_code' => 'cac_name_mismatch',
            ];
        }

        if (! $this->isReadyForReview($application) && $this->failClosedEnabled()) {
            $missing = $this->missingRequirements($application);
            $missing = array_values(array_filter($missing, fn ($m) => $m !== 'cac_status_confirmation'));
            if ($missing !== []) {
                return [
                    'ok' => false,
                    'message' => 'Complete KYB requirements before submitting.',
                    'error_code' => 'kyb_incomplete',
                    'http_status' => 422,
                ];
            }
        }

        $application->status = BusinessAccountApplication::STATUS_SUBMITTED;
        $application->submitted_at = $application->submitted_at ?? now();
        $application->progress_percent = BusinessAccountApplication::defaultProgressForStatus(BusinessAccountApplication::STATUS_SUBMITTED);
        $application->kyb_status = 'review';
        $application->save();

        return ['ok' => true, 'message' => 'Application submitted for review.'];
    }

    public function confirmCacActive(BusinessAccountApplication $application): void
    {
        $application->cac_status_confirmed_at = now();
        $this->refreshStatus($application);
        $application->save();
    }

    public function refreshStatus(BusinessAccountApplication $application): void
    {
        $application->loadMissing('kycParties');
        if ((string) $application->kyb_status === 'verified') {
            return;
        }
        $missing = $this->missingRequirements($application);
        $identityHoles = collect($missing)->filter(fn ($m) => str_starts_with((string) $m, 'party_identity_') || $m === 'ubo' || $m === 'ubo_threshold');
        if ($identityHoles->isNotEmpty()) {
            $application->kyb_status = 'incomplete';

            return;
        }
        if ($this->isReadyToApprove($application)) {
            $application->kyb_status = 'verified';
            $application->daily_limit_ngn = $application->daily_limit_ngn ?: self::STANDARD_DAILY_LIMIT;

            return;
        }
        if ($this->isReadyForReview($application) || in_array($application->status, [
            BusinessAccountApplication::STATUS_SUBMITTED,
            BusinessAccountApplication::STATUS_UNDER_REVIEW,
        ], true)) {
            $application->kyb_status = 'review';

            return;
        }
        $application->kyb_status = 'incomplete';
    }

    /**
     * Copy KYB evidence onto merchant verification rows so dashboard does not re-ask blindly.
     */
    public function mapEvidenceToBusiness(BusinessAccountApplication $application, Business $business): void
    {
        $map = [
            BusinessVerification::TYPE_CAC_CERTIFICATE => $application->cac_document_path,
            BusinessVerification::TYPE_UTILITY_BILL => $application->address_evidence_path,
        ];
        foreach ($map as $type => $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }
            BusinessVerification::query()->updateOrCreate(
                ['business_id' => $business->id, 'verification_type' => $type],
                [
                    'status' => BusinessVerification::STATUS_APPROVED,
                    'document_path' => $path,
                    'reviewed_at' => now(),
                ]
            );
        }
        $director = $application->kycParties->firstWhere('role', BusinessKycParty::ROLE_DIRECTOR);
        if ($director && $director->bvn) {
            BusinessVerification::query()->updateOrCreate(
                ['business_id' => $business->id, 'verification_type' => BusinessVerification::TYPE_BVN],
                [
                    'status' => BusinessVerification::STATUS_APPROVED,
                    'admin_notes' => 'BVN from API KYB party '.$director->id,
                    'reviewed_at' => now(),
                ]
            );
        }
        if ($director && $director->nin) {
            BusinessVerification::query()->updateOrCreate(
                ['business_id' => $business->id, 'verification_type' => BusinessVerification::TYPE_NIN],
                [
                    'status' => BusinessVerification::STATUS_APPROVED,
                    'admin_notes' => 'NIN from API KYB party '.$director->id,
                    'reviewed_at' => now(),
                ]
            );
        }
        if ($application->cac_number) {
            $business->cac_registration_number = $application->cac_number;
            $business->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeParty(BusinessKycParty $party): array
    {
        return [
            'id' => $party->id,
            'role' => $party->role,
            'person_type' => $party->person_type,
            'legal_name' => $party->legal_name,
            'dob' => $party->dob?->format('Y-m-d'),
            'nationality' => $party->nationality,
            'has_bvn' => strlen((string) $party->bvn) === 11,
            'has_nin' => strlen((string) $party->nin) === 11,
            'ownership_percent' => $party->ownership_percent !== null ? (float) $party->ownership_percent : null,
            'is_nominee' => (bool) $party->is_nominee,
            'parent_party_id' => $party->parent_party_id,
            'identity_verified' => $party->identity_verified_at !== null,
            'has_id_document' => trim((string) $party->id_document_path) !== '',
            'has_authority_document' => trim((string) $party->authority_document_path) !== '',
        ];
    }

    public function dailyLimitForWallet(WhatsappWallet $wallet): float
    {
        $app = null;
        if ($wallet->active_business_account_application_id) {
            $app = BusinessAccountApplication::query()->find($wallet->active_business_account_application_id);
        }
        if (! $app) {
            $app = BusinessAccountApplication::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->where('status', BusinessAccountApplication::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();
        }
        if ($app && (string) $app->kyb_status === 'verified') {
            if ($app->edd_required && $app->edd_approved_at === null) {
                return (float) ($app->daily_limit_ngn ?: self::STANDARD_DAILY_LIMIT);
            }

            return (float) ($app->daily_limit_ngn ?: self::STANDARD_DAILY_LIMIT);
        }

        return 0.0;
    }

    private function nameConflictsWithCac(string $businessName, string $cacNumber): bool
    {
        $normName = strtolower(preg_replace('/[^a-z0-9]+/i', '', $businessName) ?? '');
        $normCac = strtolower(preg_replace('/[^a-z0-9]+/i', '', $cacNumber) ?? '');

        return $normName === $normCac;
    }
}
