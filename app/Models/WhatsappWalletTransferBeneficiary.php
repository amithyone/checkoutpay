<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletTransferBeneficiary extends Model
{
    public const KIND_BANK = 'bank';

    public const KIND_P2P = 'p2p';

    protected $table = 'whatsapp_wallet_transfer_beneficiaries';

    protected $fillable = [
        'whatsapp_wallet_id',
        'kind',
        'destination_key',
        'account_number',
        'bank_code',
        'phone_e164',
        'display_name',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public static function bankDestinationKey(string $bankCode, string $accountNumber): string
    {
        $acct = preg_replace('/\D+/', '', $accountNumber) ?? '';
        $code = strtoupper(trim($bankCode));

        return 'bank:'.$code.':'.$acct;
    }

    public static function p2pDestinationKey(string $phoneE164): string
    {
        return 'p2p:'.preg_replace('/\D+/', '', $phoneE164);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'kind' => (string) $this->kind,
            'account_number' => $this->account_number,
            'bank_code' => $this->bank_code,
            'phone_e164' => $this->phone_e164,
            'display_name' => $this->display_name,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
