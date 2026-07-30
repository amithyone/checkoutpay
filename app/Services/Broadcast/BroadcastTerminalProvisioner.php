<?php

namespace App\Services\Broadcast;

use App\Models\Business;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BroadcastTerminalProvisioner
{
    public function __construct(
        private readonly BroadcastSignatureVerifier $signatures,
    ) {}

    public function terminalIdFor(Business $business): string
    {
        $publicId = (string) ($business->business_id ?: $business->id);

        return 'CP-'.preg_replace('/[^A-Za-z0-9._-]/', '', $publicId);
    }

    public function merchantIdFor(Business $business): string
    {
        return 'MCH-'.$this->terminalIdFor($business);
    }

    public function findForBusiness(Business $business): ?object
    {
        return DB::table('broadcast_terminals')
            ->where('business_id', $business->id)
            ->orderBy('terminal_id')
            ->first();
    }

    /**
     * @return array{account_number: string, bank_name: string, bank_code: ?string, masked_account_suffix: string}|null
     */
    public function resolveSettlementAccount(Business $business): ?array
    {
        $primary = $business->getPrimaryAccountNumber();
        if ($primary?->account_number) {
            return $this->formatAccount(
                (string) $primary->account_number,
                (string) ($primary->bank_name ?: $business->bank_name ?: 'CheckoutPay'),
                $business->bank_code ?: $business->rubies_business_bank_code,
            );
        }

        if ($business->account_number) {
            return $this->formatAccount(
                (string) $business->account_number,
                (string) ($business->bank_name ?: 'CheckoutPay'),
                $business->bank_code,
            );
        }

        if ($business->rubies_business_account_number) {
            return $this->formatAccount(
                (string) $business->rubies_business_account_number,
                (string) ($business->rubies_business_bank_name ?: $business->bank_name ?: 'CheckoutPay'),
                $business->rubies_business_bank_code ?: $business->bank_code,
            );
        }

        return null;
    }

    /**
     * @return array{terminal: object, signing_key: ?string}
     */
    public function provision(Business $business, bool $active = true): array
    {
        $settlement = $this->resolveSettlementAccount($business);
        if ($settlement === null) {
            throw new \RuntimeException('A settlement account number is required before enabling Pay at shop.');
        }

        $terminalId = $this->terminalIdFor($business);
        $merchantId = $this->merchantIdFor($business);
        $existing = DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->first();
        $now = now();
        $revealedSigningKey = null;

        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($settlement['bank_name'])));

        $row = [
            'merchant_id' => $merchantId,
            'merchant_name' => $business->name,
            'bank_name' => $settlement['bank_name'],
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => $settlement['masked_account_suffix'],
            'account_number' => $settlement['account_number'],
            'recipient_bank_code' => $settlement['bank_code'],
            'business_id' => $business->id,
            'signature_alg' => 'ED25519',
            'active' => $active ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($existing) {
            if (empty($existing->public_key)) {
                $keypair = $this->signatures->generateEd25519Keypair();
                $row['public_key'] = $keypair['public_key'];
                $row['signing_key'] = Crypt::encryptString($keypair['signing_key']);
                $revealedSigningKey = $keypair['signing_key'];
            }
            if (empty($existing->api_key)) {
                $row['api_key'] = 'bk_'.Str::lower(Str::random(32));
            }
            DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->update($row);
        } else {
            $keypair = $this->signatures->generateEd25519Keypair();
            $row['public_key'] = $keypair['public_key'];
            $row['signing_key'] = Crypt::encryptString($keypair['signing_key']);
            $row['api_key'] = 'bk_'.Str::lower(Str::random(32));
            $row['terminal_id'] = $terminalId;
            $row['created_at'] = $now;
            DB::table('broadcast_terminals')->insert($row);
            $revealedSigningKey = $keypair['signing_key'];
        }

        $terminal = DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->first();

        return [
            'terminal' => $terminal,
            'signing_key' => $revealedSigningKey,
        ];
    }

    public function setActive(Business $business, bool $active): void
    {
        $terminalId = $this->terminalIdFor($business);
        DB::table('broadcast_terminals')
            ->where('business_id', $business->id)
            ->orWhere('terminal_id', $terminalId)
            ->update([
                'active' => $active ? 1 : 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{terminal: object, signing_key: string}
     */
    public function regenerateSigningKey(Business $business): array
    {
        $terminalId = $this->terminalIdFor($business);
        $terminal = DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->first();
        if (! $terminal) {
            throw new \RuntimeException('Terminal not provisioned yet.');
        }

        $keypair = $this->signatures->generateEd25519Keypair();
        DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->update([
            'public_key' => $keypair['public_key'],
            'signing_key' => Crypt::encryptString($keypair['signing_key']),
            'updated_at' => now(),
        ]);

        return [
            'terminal' => DB::table('broadcast_terminals')->where('terminal_id', $terminalId)->first(),
            'signing_key' => $keypair['signing_key'],
        ];
    }

    public function decryptSigningKey(?object $terminal): ?string
    {
        if (! $terminal || empty($terminal->signing_key)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $terminal->signing_key);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{account_number: string, bank_name: string, bank_code: ?string, masked_account_suffix: string}
     */
    private function formatAccount(string $accountNumber, string $bankName, ?string $bankCode): array
    {
        $digits = preg_replace('/\D/', '', $accountNumber) ?: $accountNumber;
        $suffix = substr($digits, -4);
        if (strlen($suffix) !== 4) {
            $suffix = '0000';
        }

        return [
            'account_number' => $digits,
            'bank_name' => $bankName,
            'bank_code' => $bankCode,
            'masked_account_suffix' => '***'.$suffix,
        ];
    }
}
