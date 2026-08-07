<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use Illuminate\Support\Carbon;

final class OverdraftEligibilityService
{
    public const TIER_1 = 'tier_1';

    public const TIER_2 = 'tier_2';

    /**
     * @return array{volume_90d: float, tier: ?string, tier_max_limit: float, source: string}
     */
    public function computeForBusiness(Business $business): array
    {
        $volume = $this->rollingOutboundVolume90d($business);
        $tier = $this->resolveTier($volume);
        $max = $this->tierMaxLimit($tier);

        return [
            'volume_90d' => $volume,
            'tier' => $tier,
            'tier_max_limit' => $max,
            'source' => 'business',
        ];
    }

    /**
     * Personal wallet eligibility from that wallet's own 90-day outbound spend.
     *
     * @return array{volume_90d: float, tier: ?string, tier_max_limit: float, source: string}
     */
    public function computeForPersonalWallet(WhatsappWallet $wallet): array
    {
        $volume = $this->rollingPersonalOutboundVolume90d($wallet);
        $tier = $this->resolveTier($volume);
        $max = $this->tierMaxLimit($tier);

        return [
            'volume_90d' => $volume,
            'tier' => $tier,
            'tier_max_limit' => $max,
            'source' => 'personal',
        ];
    }

    /**
     * Business wallet → business volume; otherwise personal wallet volume.
     *
     * @return array{volume_90d: float, tier: ?string, tier_max_limit: float, source: string}
     */
    public function computeForWallet(WhatsappWallet $wallet, ?Business $business = null): array
    {
        if ($business instanceof Business) {
            return $this->computeForBusiness($business);
        }

        return $this->computeForPersonalWallet($wallet);
    }

    public function syncBusiness(Business $business): Business
    {
        $result = $this->computeForBusiness($business);
        $business->overdraft_volume_90d = $result['volume_90d'];
        $business->overdraft_volume_tier = $result['tier'];
        $business->overdraft_volume_computed_at = now();

        if ($result['tier'] !== null) {
            $business->overdraft_eligible = true;
        }

        $business->save();

        return $business->fresh();
    }

    public function rollingOutboundVolume90d(Business $business): float
    {
        $since = Carbon::now()->subDays(90);
        $walletIds = WhatsappWallet::query()
            ->where('linked_business_id', $business->id)
            ->pluck('id');

        $txnVolume = 0.0;
        if ($walletIds->isNotEmpty()) {
            $txnVolume = (float) WhatsappWalletTransaction::query()
                ->whereIn('whatsapp_wallet_id', $walletIds)
                ->where('ledger_scope', ConsumerWalletTransactionScope::SCOPE_BUSINESS)
                ->whereIn('type', $this->outboundTypes())
                ->where('created_at', '>=', $since)
                ->sum('amount');
        }

        $withdrawalVolume = (float) WithdrawalRequest::query()
            ->where('business_id', $business->id)
            ->where('status', 'approved')
            ->where('created_at', '>=', $since)
            ->sum('amount');

        return round($txnVolume + $withdrawalVolume, 2);
    }

    public function rollingPersonalOutboundVolume90d(WhatsappWallet $wallet): float
    {
        $since = Carbon::now()->subDays(90);

        $txnVolume = (float) WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where(function ($q) {
                $q->where('ledger_scope', ConsumerWalletTransactionScope::SCOPE_PERSONAL)
                    ->orWhereNull('ledger_scope')
                    ->orWhere('ledger_scope', '');
            })
            ->whereIn('type', $this->outboundTypes())
            ->where('created_at', '>=', $since)
            ->sum('amount');

        return round($txnVolume, 2);
    }

    /**
     * @return list<string>
     */
    private function outboundTypes(): array
    {
        return [
            WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            WhatsappWalletTransaction::TYPE_P2P_DEBIT,
            WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
            WhatsappWalletTransaction::TYPE_VTU_DATA,
            WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            WhatsappWalletTransaction::TYPE_VTU_CABLE,
            WhatsappWalletTransaction::TYPE_VTU_BETTING,
            WhatsappWalletTransaction::TYPE_PARTNER_MERCHANT_PAY,
        ];
    }

    public function resolveTier(float $volume): ?string
    {
        $t2 = $this->tier2Threshold();
        $t1 = $this->tier1Threshold();

        if ($volume >= $t2) {
            return self::TIER_2;
        }
        if ($volume >= $t1) {
            return self::TIER_1;
        }

        return null;
    }

    public function tier1Threshold(): float
    {
        return (float) Setting::get('overdraft_tier_1_volume_threshold', 5_000_000);
    }

    public function tier2Threshold(): float
    {
        return (float) Setting::get('overdraft_tier_2_volume_threshold', 10_000_000);
    }

    public function tierMaxLimit(?string $tier): float
    {
        if ($tier === self::TIER_2) {
            return (float) Setting::get('overdraft_tier_2_max_limit', 10_000_000);
        }
        if ($tier === self::TIER_1) {
            return (float) Setting::get('overdraft_tier_1_max_limit', 5_000_000);
        }

        return 0.0;
    }

    /**
     * @return list<float>
     */
    public function allowedLimitsForBusiness(Business $business): array
    {
        $all = [200000, 500000, 1000000, 2000000, 5000000, 10000000];
        $max = $this->tierMaxLimit($business->overdraft_volume_tier);

        if ($max <= 0) {
            return $all;
        }

        return array_values(array_filter($all, fn (float $v) => $v <= $max + 0.01));
    }
}
