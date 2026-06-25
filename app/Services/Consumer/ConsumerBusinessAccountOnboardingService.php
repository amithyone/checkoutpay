<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\BusinessAccountApplication;
use App\Models\BusinessNameRegistration;
use App\Models\BusinessWebsite;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ConsumerBusinessAccountOnboardingService
{
    /**
     * @return array<string, mixed>
     */
    public function configPayload(): array
    {
        if (! $this->isLive()) {
            return [
                'available' => false,
                'coming_soon_message' => (string) config(
                    'consumer_wallet.business_account_onboarding.coming_soon_message',
                    'Business account onboarding coming soon.'
                ),
            ];
        }

        $currency = strtoupper((string) config('consumer_wallet.business_account_onboarding.fee_currency', 'NGN'));
        $fee = round((float) config('consumer_wallet.business_account_onboarding.fee_amount', 0), 2);

        return [
            'available' => true,
            'fee_amount' => $fee,
            'fee_currency' => $currency,
            'fee_label' => $fee > 0
                ? WhatsappWalletMoneyFormatter::format($fee, $currency)
                : null,
            'account_plans' => [
                [
                    'id' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
                    'label' => 'Business account only',
                    'description' => 'Accept payments, use the business dashboard, and API keys.',
                ],
                [
                    'id' => BusinessAccountApplication::PLAN_PAYMENTS_AND_WEB,
                    'label' => 'Business account + web service',
                    'description' => 'Everything in business account plus public catalog pages on CheckoutPay.',
                ],
            ],
            'service_categories' => (array) config('consumer_wallet.business_account_onboarding.service_categories', []),
            'dashboard_login_url' => $this->dashboardLoginUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function index(WhatsappWallet $wallet): array
    {
        $wallet = $wallet->fresh(['linkedBusiness']);
        $applications = BusinessAccountApplication::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (BusinessAccountApplication $row) => $this->serializeApplication($row))
            ->values()
            ->all();

        $active = collect($applications)->first(fn (array $row) => in_array(
            $row['status'] ?? '',
            [
                BusinessAccountApplication::STATUS_SUBMITTED,
                BusinessAccountApplication::STATUS_UNDER_REVIEW,
                BusinessAccountApplication::STATUS_APPROVED,
                BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
            ],
            true
        ));

        return [
            'config' => $this->configPayload(),
            'applications' => $applications,
            'active_application' => $active,
            'can_apply' => $this->canApply($wallet),
            'linked_business' => $this->linkedBusinessSummary($wallet),
            'prefill' => $this->prefillFromBnr($wallet),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http_status?: int}
     */
    public function submit(WhatsappWallet $wallet, array $input, ?UploadedFile $cacDocument = null): array
    {
        if (! $this->isLive()) {
            return [
                'ok' => false,
                'message' => (string) config(
                    'consumer_wallet.business_account_onboarding.coming_soon_message',
                    'Business account onboarding coming soon.'
                ),
                'http_status' => 403,
            ];
        }

        if (! $this->canApply($wallet)) {
            if ($wallet->linked_business_id) {
                return ['ok' => false, 'message' => 'This wallet already has a linked business account.', 'http_status' => 422];
            }

            return ['ok' => false, 'message' => 'You already have a business account application in progress.', 'http_status' => 422];
        }

        $plan = (string) ($input['account_plan'] ?? '');
        if (! in_array($plan, [BusinessAccountApplication::PLAN_PAYMENTS_ONLY, BusinessAccountApplication::PLAN_PAYMENTS_AND_WEB], true)) {
            return ['ok' => false, 'message' => 'Select a valid account plan.', 'http_status' => 422];
        }

        $businessName = trim((string) ($input['business_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $websiteUrl = trim((string) ($input['website_url'] ?? ''));

        if (strlen($businessName) < 3) {
            return ['ok' => false, 'message' => 'Business name must be at least 3 characters.', 'http_status' => 422];
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid business email.', 'http_status' => 422];
        }
        if (Business::query()->where('email', $email)->exists()) {
            return ['ok' => false, 'message' => 'That email is already registered for a business account.', 'http_status' => 422];
        }
        if ($address === '') {
            return ['ok' => false, 'message' => 'Business address is required.', 'http_status' => 422];
        }
        if ($plan === BusinessAccountApplication::PLAN_PAYMENTS_AND_WEB && $websiteUrl === '') {
            return ['ok' => false, 'message' => 'Website URL is required for web service plans.', 'http_status' => 422];
        }

        $categories = $this->normalizeCategories($input['service_categories'] ?? [], $plan);
        $fee = round((float) config('consumer_wallet.business_account_onboarding.fee_amount', 0), 2);
        $currency = strtoupper((string) config('consumer_wallet.business_account_onboarding.fee_currency', 'NGN'));

        if ($fee > 0 && (float) $wallet->balance < $fee) {
            return ['ok' => false, 'message' => 'Insufficient wallet balance', 'http_status' => 422];
        }

        $reference = $this->nextReference();
        $publicId = 'baa_'.Str::lower((string) Str::ulid());
        $cacPath = null;
        $application = null;

        try {
            DB::transaction(function () use (
                $wallet,
                $plan,
                $categories,
                $businessName,
                $email,
                $phone,
                $address,
                $websiteUrl,
                $fee,
                $currency,
                $reference,
                $publicId,
                $cacDocument,
                &$cacPath,
                &$application,
            ) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }

                if ($fee > 0) {
                    $w->resetDailyTransferIfNeeded();
                    $check = $w->canDebit($fee);
                    if (! ($check['ok'] ?? false)) {
                        throw new \RuntimeException('insufficient_balance');
                    }
                }

                if (BusinessAccountApplication::query()
                    ->where('whatsapp_wallet_id', $w->id)
                    ->whereIn('status', [
                        BusinessAccountApplication::STATUS_SUBMITTED,
                        BusinessAccountApplication::STATUS_UNDER_REVIEW,
                        BusinessAccountApplication::STATUS_APPROVED,
                        BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
                    ])
                    ->exists()) {
                    throw new \RuntimeException('duplicate_application');
                }

                if ($w->linked_business_id) {
                    throw new \RuntimeException('already_linked');
                }

                $txnId = null;
                $newBal = (float) $w->balance;

                if ($fee > 0) {
                    $newBal = round((float) $w->balance - $fee, 2);
                    $w->balance = $newBal;
                    $w->daily_transfer_total = round((float) $w->daily_transfer_total + $fee, 2);
                    $w->daily_transfer_for_date = now()->toDateString();
                    $w->save();

                    $txn = WhatsappWalletTransaction::query()->create([
                        'whatsapp_wallet_id' => $w->id,
                        'sender_name' => $w->normalizedSenderName(),
                        'type' => WhatsappWalletTransaction::TYPE_BUSINESS_ACCOUNT_ONBOARDING_FEE,
                        'amount' => -$fee,
                        'balance_after' => $newBal,
                        'external_reference' => $reference,
                        'meta' => [
                            'channel' => 'consumer_api',
                            'business_account_application_public_id' => $publicId,
                            'business_name' => $businessName,
                        ],
                    ]);
                    $txnId = $txn->id;
                }

                $application = BusinessAccountApplication::query()->create([
                    'public_id' => $publicId,
                    'whatsapp_wallet_id' => $w->id,
                    'reference' => $reference,
                    'account_plan' => $plan,
                    'service_categories' => $categories,
                    'business_name' => $businessName,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'address' => $address,
                    'website_url' => $websiteUrl !== '' ? $websiteUrl : null,
                    'cac_document_path' => '',
                    'status' => BusinessAccountApplication::STATUS_SUBMITTED,
                    'progress_percent' => BusinessAccountApplication::defaultProgressForStatus(BusinessAccountApplication::STATUS_SUBMITTED),
                    'fee_amount' => $fee,
                    'fee_currency' => $currency,
                    'fee_transaction_id' => $txnId,
                    'submitted_at' => now(),
                ]);

                if ($cacDocument instanceof UploadedFile) {
                    $cacPath = $cacDocument->store(
                        'business-account-applications/'.$w->id.'/'.$application->id,
                        'local'
                    );
                    $application->update(['cac_document_path' => $cacPath]);
                }

                $w->update(['active_business_account_application_id' => $application->id]);
            });
        } catch (\Throwable $e) {
            if ($cacPath !== null) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($cacPath);
            }

            return match ($e->getMessage()) {
                'insufficient_balance' => ['ok' => false, 'message' => 'Insufficient wallet balance', 'http_status' => 422],
                'duplicate_application' => ['ok' => false, 'message' => 'You already have a business account application in progress.', 'http_status' => 422],
                'already_linked' => ['ok' => false, 'message' => 'This wallet already has a linked business account.', 'http_status' => 422],
                'wallet_missing' => ['ok' => false, 'message' => 'Wallet not found.', 'http_status' => 404],
                default => tap(
                    ['ok' => false, 'message' => 'Could not submit business account application.', 'http_status' => 422],
                    static fn () => Log::error('consumer.business_account_onboarding.submit_failed', [
                        'wallet_id' => $wallet->id,
                        'error' => $e->getMessage(),
                    ])
                ),
            };
        }

        /** @var BusinessAccountApplication $application */
        return [
            'ok' => true,
            'message' => 'Application submitted.',
            'data' => $this->serializeApplication($application->fresh()),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http_status?: int}
     */
    public function setPassword(WhatsappWallet $wallet, string $password, string $passwordConfirmation): array
    {
        $application = BusinessAccountApplication::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereIn('status', [
                BusinessAccountApplication::STATUS_APPROVED,
                BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
            ])
            ->orderByDesc('id')
            ->first();

        if (! $application) {
            return ['ok' => false, 'message' => 'No approved application awaiting password setup.', 'http_status' => 422];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.', 'http_status' => 422];
        }
        if ($password !== $passwordConfirmation) {
            return ['ok' => false, 'message' => 'Password confirmation does not match.', 'http_status' => 422];
        }

        $business = $application->linkedBusiness;
        if (! $business) {
            return ['ok' => false, 'message' => 'Linked business account not found.', 'http_status' => 422];
        }

        $business->update(['password' => Hash::make($password)]);
        $application->update([
            'status' => BusinessAccountApplication::STATUS_ACTIVE,
            'progress_percent' => 100,
            'password_set_at' => now(),
        ]);

        return [
            'ok' => true,
            'message' => 'Business dashboard password set.',
            'data' => [
                'application' => $this->serializeApplication($application->fresh()),
                'dashboard_login_email' => $business->email,
                'dashboard_login_url' => $this->dashboardLoginUrl(),
            ],
        ];
    }

    public function isLive(): bool
    {
        return (bool) config('consumer_wallet.business_account_onboarding.enabled', false);
    }

    public function canApply(WhatsappWallet $wallet): bool
    {
        if ($wallet->linked_business_id) {
            return false;
        }

        return ! BusinessAccountApplication::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereIn('status', [
                BusinessAccountApplication::STATUS_SUBMITTED,
                BusinessAccountApplication::STATUS_UNDER_REVIEW,
                BusinessAccountApplication::STATUS_APPROVED,
                BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
            ])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeApplication(BusinessAccountApplication $row): array
    {
        return [
            'id' => (string) $row->public_id,
            'reference' => (string) $row->reference,
            'account_plan' => (string) $row->account_plan,
            'service_categories' => (array) ($row->service_categories ?? []),
            'business_name' => (string) $row->business_name,
            'email' => (string) $row->email,
            'phone' => $row->phone,
            'address' => $row->address,
            'website_url' => $row->website_url,
            'status' => (string) $row->status,
            'progress_percent' => (int) $row->progress_percent,
            'status_label' => $row->statusDisplayLabel(),
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'approved_at' => $row->approved_at?->toIso8601String(),
            'password_set_at' => $row->password_set_at?->toIso8601String(),
            'rejected_reason' => $row->rejected_reason,
            'fee_amount' => $row->fee_amount !== null ? (float) $row->fee_amount : null,
            'fee_currency' => $row->fee_currency,
            'linked_business_id' => $row->linked_business_id,
            'dashboard_login_url' => $this->dashboardLoginUrl(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkedBusinessSummary(WhatsappWallet $wallet): ?array
    {
        $business = $wallet->linkedBusiness;
        if (! $business) {
            return null;
        }

        return [
            'id' => (int) $business->id,
            'name' => (string) $business->name,
            'email' => (string) $business->email,
            'dashboard_login_url' => $this->dashboardLoginUrl(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function prefillFromBnr(WhatsappWallet $wallet): ?array
    {
        $bnr = BusinessNameRegistration::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', BusinessNameRegistration::STATUS_APPROVED)
            ->orderByDesc('id')
            ->first();

        if (! $bnr) {
            return null;
        }

        return [
            'business_name' => (string) ($bnr->approved_business_name ?: $bnr->proposed_name),
            'email' => (string) $bnr->owner_email,
            'phone' => (string) $bnr->owner_phone,
            'address' => (string) $bnr->business_address,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeCategories(mixed $raw, string $plan): array
    {
        $allowed = BusinessAccountApplication::SERVICE_CATEGORIES;
        $selected = is_array($raw) ? $raw : [];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($v) => is_string($v) ? strtolower(trim($v)) : '',
            $selected
        ))));

        $normalized = array_values(array_intersect($normalized, $allowed));
        if (! in_array('payments', $normalized, true)) {
            $normalized[] = 'payments';
        }

        if ($plan === BusinessAccountApplication::PLAN_PAYMENTS_ONLY) {
            return ['payments'];
        }

        return $normalized;
    }

    private function nextReference(): string
    {
        $year = now()->format('Y');
        $latest = BusinessAccountApplication::query()
            ->where('reference', 'like', 'BAA-'.$year.'-%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/^BAA-\d{4}-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('BAA-%s-%05d', $year, $seq);
    }

    private function dashboardLoginUrl(): string
    {
        $path = (string) config('consumer_wallet.business_account_onboarding.dashboard_login_url', '/dashboard/login');

        return str_starts_with($path, 'http') ? $path : url($path);
    }
}
