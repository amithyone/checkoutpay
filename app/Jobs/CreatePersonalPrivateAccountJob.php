<?php

namespace App\Jobs;

use App\Models\WhatsappWallet;
use App\Services\MevonPay\MevonIdentityVerificationService;
use App\Services\MevonPay\MevonPrivateAccountService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreatePersonalPrivateAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<string, mixed>  $options  Optional: account_kind=business, business_name, cac
     */
    public function __construct(
        public int $walletId,
        public array $options = [],
    ) {
        $this->onQueue(PrivateAccountProvisionService::QUEUE_KYC_PROVISION);
    }

    public function handle(
        MevonPrivateAccountService $privateAccount,
        MevonIdentityVerificationService $identityVerify,
    ): void {
        $wallet = WhatsappWallet::query()->find($this->walletId);
        if ($wallet === null) {
            return;
        }

        if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return;
        }

        $wallet->update([
            'private_account_provision_status' => PrivateAccountProvisionService::STATUS_PROCESSING,
        ]);

        $apiPhone = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        if ($apiPhone === null) {
            $this->markFailed($wallet, 'Could not read wallet phone number.');

            return;
        }

        $dobYmd = optional($wallet->kyc_dob)?->format('Y-m-d') ?? '';
        $email = strtolower(trim((string) $wallet->kyc_email));
        $bvn = preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '';
        $nin = preg_replace('/\D+/', '', (string) $wallet->kyc_nin) ?? '';
        $isBusiness = ($this->options['account_kind'] ?? '') === 'business';

        if ($isBusiness) {
            if (! $identityVerify->isBvnConfigured()) {
                $this->markFailed($wallet, 'Mevon BVN verification is not configured.');

                return;
            }
        } elseif (! $identityVerify->isBvnConfigured() && ! $identityVerify->isNinConfigured()) {
            $this->markFailed($wallet, 'Mevon identity verification is not configured.');

            return;
        }

        $fname = trim((string) $wallet->kyc_fname);
        $lname = trim((string) $wallet->kyc_lname);

        $identityResult = $identityVerify->verifyPersonal(
            firstName: $fname,
            lastName: $lname,
            dobYmd: $dobYmd,
            bvn11: strlen($bvn) === 11 ? $bvn : null,
            nin11: strlen($nin) === 11 ? $nin : null,
            submittedFullName: $isBusiness ? null : trim($fname.' '.$lname),
        );

        if (! $identityResult['ok']) {
            $this->markFailed($wallet, (string) $identityResult['message']);
            throw new \RuntimeException((string) $identityResult['message']);
        }

        try {
            if ($isBusiness) {
                $va = $privateAccount->createBusinessAccount(
                    businessName: (string) ($this->options['business_name'] ?? $wallet->kyc_cac ?? ''),
                    cac: strtoupper(trim((string) ($this->options['cac'] ?? $wallet->kyc_cac ?? ''))),
                    phoneLocal11: $apiPhone,
                    dobYmd: $dobYmd,
                    email: $email,
                    bvn11: strlen($bvn) === 11 ? $bvn : null,
                    nin11: strlen($nin) === 11 ? $nin : null,
                );
            } else {
                $va = $privateAccount->createPersonalAccount(
                    fname: $fname,
                    lname: $lname,
                    phoneLocal11: $apiPhone,
                    dobYmd: $dobYmd,
                    email: $email,
                    bvn11: strlen($bvn) === 11 ? $bvn : null,
                    nin11: strlen($nin) === 11 ? $nin : null,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('private_account.personal_job_failed', [
                'wallet_id' => $wallet->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($wallet, $e->getMessage());
            throw $e;
        }

        $wallet->refresh();
        if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return;
        }

        $verifiedSenderName = $wallet->resolveSenderNameAfterTier2(
            (string) ($va['account_name'] ?? ''),
            $isBusiness ? null : $fname,
            $isBusiness ? null : $lname,
        );

        $update = [
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'mevon_virtual_account_number' => $va['account_number'],
            'mevon_account_name' => trim((string) ($va['account_name'] ?? '')) ?: null,
            'mevon_bank_name' => $va['bank_name'] ?: null,
            'mevon_bank_code' => $va['bank_code'] ?: null,
            'mevon_reference' => $va['reference'] !== '' ? $va['reference'] : $wallet->mevon_reference,
            'tier2_provisioned_at' => now(),
            'private_account_provision_status' => PrivateAccountProvisionService::STATUS_COMPLETED,
            'private_account_provision_error' => null,
            'kyc_verified_at' => now(),
        ];

        if ($verifiedSenderName !== null) {
            $update['sender_name'] = $verifiedSenderName;
        }

        $wallet->update($update);

        Log::info('private_account.personal_completed', [
            'wallet_id' => $wallet->id,
            'account_suffix' => substr((string) $va['account_number'], -4),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $wallet = WhatsappWallet::query()->find($this->walletId);
        if ($wallet === null || trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return;
        }

        $message = $exception?->getMessage() ?? 'Account provisioning failed after retries.';
        $this->markPermanentlyFailed($wallet, $message);
    }

    private function markFailed(WhatsappWallet $wallet, string $message): void
    {
        $wallet->update([
            'private_account_provision_status' => PrivateAccountProvisionService::STATUS_FAILED,
            'private_account_provision_error' => $message,
        ]);
    }

    private function markPermanentlyFailed(WhatsappWallet $wallet, string $message): void
    {
        $wallet->update([
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'kyc_verified_at' => null,
            'private_account_provision_status' => PrivateAccountProvisionService::STATUS_FAILED,
            'private_account_provision_error' => $message,
        ]);
    }
}
