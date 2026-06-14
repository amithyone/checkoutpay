<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;
use App\Services\Whatsapp\WhatsappWalletRegionConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WhatsappWalletAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.whatsapp-wallet.index', $this->dashboardMetrics());
    }

    public function settings(): View
    {
        return view('admin.whatsapp-wallet.settings', $this->settingsFormData());
    }

    public function wallets(Request $request): View
    {
        $query = WhatsappWallet::query()->orderByDesc('id');

        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        $status = (string) $request->query('status', '');
        if ($status === WhatsappWallet::STATUS_ACTIVE || $status === WhatsappWallet::STATUS_SUSPENDED) {
            $query->where('status', $status);
        }

        $tier = $request->query('tier');
        if ($tier !== null && $tier !== '' && is_numeric($tier)) {
            $query->where('tier', (int) $tier);
        }

        if ($request->boolean('needs_setup')) {
            $query->where(function ($q): void {
                $q->where(function ($q2): void {
                    $q2->whereNull('pin_hash')->orWhere('pin_hash', '');
                })->orWhere(function ($q2): void {
                    $q2->whereNull('sender_name')->orWhere('sender_name', '');
                });
            });
        }

        if ($request->boolean('manual_chat')) {
            $query->where('admin_bot_paused', true);
        }

        $wallets = $query->paginate(25)->withQueryString();

        return view('admin.whatsapp-wallet.wallets.index', [
            'wallets' => $wallets,
        ]);
    }

    public function showWallet(WhatsappWallet $wallet): View
    {
        $wallet->resetDailyTransferIfNeeded();

        $wallet->loadCount([
            'transactions as bank_transfers_count' => fn ($q) => $q->where('type', WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT),
            'transactions as p2p_count' => fn ($q) => $q->p2p(),
            'transactions as topups_count' => fn ($q) => $q->where('type', WhatsappWalletTransaction::TYPE_TOPUP),
        ]);

        $recentTx = $wallet->transactions()
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $pendingPayouts = $wallet->transactions()
            ->bankTransferOut()
            ->payoutPending()
            ->where('created_at', '>=', now()->subHours(48))
            ->count();

        return view('admin.whatsapp-wallet.wallets.show', [
            'wallet' => $wallet,
            'recentTx' => $recentTx,
            'pendingPayouts' => $pendingPayouts,
        ]);
    }

    public function updateWalletStatus(Request $request, WhatsappWallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.WhatsappWallet::STATUS_ACTIVE.','.WhatsappWallet::STATUS_SUSPENDED,
        ]);

        $wallet->update(['status' => $validated['status']]);

        return redirect()
            ->route('admin.whatsapp-wallet.wallets.show', $wallet)
            ->with('success', $validated['status'] === WhatsappWallet::STATUS_ACTIVE
                ? 'Wallet reactivated.'
                : 'Wallet suspended.');
    }

    public function updateWalletBotPause(Request $request, WhatsappWallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'admin_bot_paused' => 'required|boolean',
        ]);

        $wallet->update(['admin_bot_paused' => (bool) $validated['admin_bot_paused']]);

        return redirect()
            ->route('admin.whatsapp-wallet.wallets.show', $wallet)
            ->with('success', $wallet->admin_bot_paused
                ? 'Manual chat mode enabled — the bot will not auto-reply until the user sends START BOT or you resume it here.'
                : 'Automated bot replies resumed for this user.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardMetrics(): array
    {
        $walletTotal = WhatsappWallet::query()->count();
        $walletsWithPin = WhatsappWallet::query()->whereNotNull('pin_hash')->where('pin_hash', '!=', '')->count();
        $txTotal = WhatsappWalletTransaction::query()->count();
        $txLast7d = WhatsappWalletTransaction::query()->where('created_at', '>=', now()->subDays(7))->count();
        $txLast30d = WhatsappWalletTransaction::query()->where('created_at', '>=', now()->subDays(30))->count();

        $txByType = WhatsappWalletTransaction::query()
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->toArray();

        $walletsByCountry = [];
        WhatsappWallet::query()->select('id', 'phone_e164')->orderBy('id')->chunk(500, function ($chunk) use (&$walletsByCountry): void {
            foreach ($chunk as $w) {
                $cc = $this->countryFromPhoneE164((string) $w->phone_e164);
                $walletsByCountry[$cc] = ($walletsByCountry[$cc] ?? 0) + 1;
            }
        });
        ksort($walletsByCountry);

        $recentTx = WhatsappWalletTransaction::query()
            ->with(['wallet:id,phone_e164'])
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $regions = WhatsappWalletRegionConfig::instances();
        $dialMap = WhatsappWalletRegionConfig::countryByDial();

        return [
            'walletTotal' => $walletTotal,
            'walletsWithPin' => $walletsWithPin,
            'txTotal' => $txTotal,
            'txLast7d' => $txLast7d,
            'txLast30d' => $txLast30d,
            'txByType' => $txByType,
            'walletsByCountry' => $walletsByCountry,
            'recentTx' => $recentTx,
            'regions' => is_array($regions) ? $regions : [],
            'dialMap' => is_array($dialMap) ? $dialMap : [],
            'failedPayoutCount' => WhatsappWalletTransaction::countFailedBankPayoutsRecent(),
            'pendingPayoutCount' => WhatsappWalletTransaction::countPendingBankPayoutsRecent(),
            'p2pCount7d' => WhatsappWalletTransaction::query()->p2p()->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFormData(): array
    {
        $regions = WhatsappWalletRegionConfig::instances();
        $dialMap = WhatsappWalletRegionConfig::countryByDial();
        $wa = Setting::getByGroup('whatsapp');
        $fxRates = WhatsappCrossBorderFxRate::query()->orderBy('from_currency')->orderBy('to_currency')->get();
        $legacyFx = $wa['whatsapp_cross_border_fx_rates_json'] ?? null;
        $legacyFxPairCount = is_array($legacyFx) ? count($legacyFx) : 0;

        return [
            'regions' => is_array($regions) ? $regions : [],
            'dialMap' => is_array($dialMap) ? $dialMap : [],
            'wa' => $wa,
            'fxRates' => $fxRates,
            'fxCurrencyCodes' => $this->fxCurrencyCodesForAdmin(),
            'legacyFxPairCount' => $legacyFxPairCount,
            'checkoutPayCodeCountries' => \App\Services\Whatsapp\WhatsappCheckoutPayCodePolicy::countryOptionsForAdmin(),
            'checkoutPayCodeEnabledCountries' => \App\Services\Whatsapp\WhatsappCheckoutPayCodePolicy::enabledCountries(),
        ];
    }

    public function updateFxRates(Request $request): RedirectResponse
    {
        $pairs = $request->input('pairs', []);
        if (! is_array($pairs)) {
            $pairs = [];
        }

        $normalized = [];
        foreach ($pairs as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $from = strtoupper(preg_replace('/\s+/', '', (string) ($row['from_currency'] ?? '')));
            $to = strtoupper(preg_replace('/\s+/', '', (string) ($row['to_currency'] ?? '')));
            $rateRaw = $row['rate'] ?? null;
            if ($from === '' && $to === '' && ($rateRaw === null || $rateRaw === '')) {
                continue;
            }
            if (strlen($from) !== 3 || strlen($to) !== 3 || ! preg_match('/^[A-Z]{3}$/', $from) || ! preg_match('/^[A-Z]{3}$/', $to)) {
                return redirect()->route('admin.whatsapp-wallet.settings')
                    ->withErrors(['fx_rates' => 'Row #'.((int) $i + 1).': use 3-letter currency codes (A–Z only), e.g. NGN, USD.'])
                    ->withInput();
            }
            if ($from === $to) {
                return redirect()->route('admin.whatsapp-wallet.settings')
                    ->withErrors(['fx_rates' => 'Row #'.((int) $i + 1).": From and To must be different (got {$from})."])
                    ->withInput();
            }
            if (! is_numeric($rateRaw) || (float) $rateRaw <= 0) {
                return redirect()->route('admin.whatsapp-wallet.settings')
                    ->withErrors(['fx_rates' => 'Row #'.((int) $i + 1).': Rate must be a positive number.'])
                    ->withInput();
            }
            $key = $from.'_'.$to;
            if (isset($normalized[$key])) {
                return redirect()->route('admin.whatsapp-wallet.settings')
                    ->withErrors(['fx_rates' => "Duplicate pair: {$from} → {$to}."])
                    ->withInput();
            }
            $normalized[$key] = [
                'from_currency' => $from,
                'to_currency' => $to,
                'rate' => (float) $rateRaw,
            ];
        }

        DB::transaction(function () use ($normalized): void {
            WhatsappCrossBorderFxRate::query()->delete();
            foreach ($normalized as $p) {
                WhatsappCrossBorderFxRate::query()->create([
                    'from_currency' => $p['from_currency'],
                    'to_currency' => $p['to_currency'],
                    'rate' => $p['rate'],
                ]);
            }
        });

        WhatsappCrossBorderP2pFxService::forgetRatesCache();

        return redirect()->route('admin.whatsapp-wallet.settings')
            ->with('success', 'Cross-border FX rates saved ('.count($normalized).' pair'.(count($normalized) === 1 ? '' : 's').').');
    }

    /**
     * @return list<string>
     */
    private function fxCurrencyCodesForAdmin(): array
    {
        $codes = [];
        $dial = WhatsappWalletRegionConfig::countryByDial();
        if (is_array($dial)) {
            foreach ($dial as $row) {
                if (is_array($row) && ! empty($row['currency'])) {
                    $codes[strtoupper((string) $row['currency'])] = true;
                }
            }
        }
        $instances = WhatsappWalletRegionConfig::instances();
        if (is_array($instances)) {
            foreach ($instances as $row) {
                if (is_array($row) && ! empty($row['currency'])) {
                    $codes[strtoupper((string) $row['currency'])] = true;
                }
            }
        }
        $list = array_keys($codes);
        sort($list);

        return $list !== [] ? $list : ['NGN', 'NAD', 'USD', 'CAD', 'GBP', 'GHS'];
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_app_url' => 'nullable|string|max:500',
            'whatsapp_public_url' => 'nullable|string|max:500',
            'whatsapp_webhook_secret' => 'nullable|string|max:500',
            'whatsapp_evolution_base_url' => 'nullable|string|max:500',
            'whatsapp_evolution_api_key' => 'nullable|string|max:500',
            'whatsapp_evolution_instance_default' => 'nullable|string|max:120',
            'whatsapp_evolution_instance_namibia' => 'nullable|string|max:120',
            'whatsapp_evolution_instance_global' => 'nullable|string|max:120',
            'whatsapp_wallet_tier1_max_balance' => 'nullable|numeric|min:0',
            'whatsapp_wallet_tier1_daily_transfer' => 'nullable|numeric|min:0',
            'whatsapp_transfer_confirm_ttl_minutes' => 'nullable|integer|min:5|max:1440',
            'whatsapp_cross_border_p2p_enabled' => 'nullable|boolean',
            'whatsapp_cross_border_fx_rate_source' => 'nullable|string|in:manual,open_er_usd',
            'whatsapp_cross_border_fx_profit_margin_percent' => 'nullable|numeric|min:0|max:99.99',
            'whatsapp_cross_border_prompt_template' => 'nullable|string|max:5000',
            'whatsapp_cross_border_disabled_message' => 'nullable|string|max:2000',
            'whatsapp_cross_border_missing_rate_message' => 'nullable|string|max:2000',
            'whatsapp_self_bank_transfer_fee_enabled' => 'nullable|boolean',
            'whatsapp_self_bank_transfer_fee_percent' => 'nullable|numeric|min:0|max:25',
        ]);

        Setting::set('whatsapp_app_url', $validated['whatsapp_app_url'] ?: null, 'string', 'whatsapp', 'Public app URL (WHATSAPP_APP_URL override)');
        Setting::set('whatsapp_public_url', $validated['whatsapp_public_url'] ?: null, 'string', 'whatsapp', 'Base URL for wallet PIN / confirm pages');
        Setting::set('whatsapp_webhook_secret', $validated['whatsapp_webhook_secret'] ?: null, 'string', 'whatsapp', 'Evolution webhook secret (optional override)');
        Setting::set('whatsapp_evolution_base_url', $validated['whatsapp_evolution_base_url'] ?: null, 'string', 'whatsapp', 'Evolution API base URL');
        Setting::set('whatsapp_evolution_api_key', $validated['whatsapp_evolution_api_key'] ?: null, 'string', 'whatsapp', 'Evolution API key');
        Setting::set('whatsapp_evolution_instance_default', $validated['whatsapp_evolution_instance_default'] ?: null, 'string', 'whatsapp', 'Evolution instance — Nigeria / default');
        Setting::set('whatsapp_evolution_instance_namibia', $validated['whatsapp_evolution_instance_namibia'] ?: null, 'string', 'whatsapp', 'Evolution instance — Namibia');
        Setting::set('whatsapp_evolution_instance_global', $validated['whatsapp_evolution_instance_global'] ?: null, 'string', 'whatsapp', 'Evolution instance — global (optional)');
        Setting::set('whatsapp_wallet_tier1_max_balance', $validated['whatsapp_wallet_tier1_max_balance'] ?? null, 'float', 'whatsapp', 'Tier 1 max wallet balance');
        Setting::set('whatsapp_wallet_tier1_daily_transfer', $validated['whatsapp_wallet_tier1_daily_transfer'] ?? null, 'float', 'whatsapp', 'Tier 1 daily transfer cap');
        Setting::set('whatsapp_transfer_confirm_ttl_minutes', $validated['whatsapp_transfer_confirm_ttl_minutes'] ?? null, 'integer', 'whatsapp', 'Web PIN / transfer confirm link TTL (minutes)');
        Setting::set('whatsapp_cross_border_p2p_enabled', $request->boolean('whatsapp_cross_border_p2p_enabled'), 'boolean', 'whatsapp', 'Cross-border P2P with FX');
        $fxSource = $validated['whatsapp_cross_border_fx_rate_source'] ?? 'manual';
        Setting::set('whatsapp_cross_border_fx_rate_source', $fxSource, 'string', 'whatsapp', 'FX rate source: manual table vs live USD (open.er-api.com)');
        Setting::set(
            'whatsapp_cross_border_fx_profit_margin_percent',
            $validated['whatsapp_cross_border_fx_profit_margin_percent'] ?? 0,
            'float',
            'whatsapp',
            'Global FX margin % (recipient gets (100−p)% of base conversion)'
        );
        Setting::set('whatsapp_cross_border_prompt_template', $validated['whatsapp_cross_border_prompt_template'] ?: null, 'string', 'whatsapp', 'Cross-border prompt template');
        Setting::set('whatsapp_cross_border_disabled_message', $validated['whatsapp_cross_border_disabled_message'] ?: null, 'string', 'whatsapp', 'Message when cross-border off');
        Setting::set('whatsapp_cross_border_missing_rate_message', $validated['whatsapp_cross_border_missing_rate_message'] ?: null, 'string', 'whatsapp', 'Message when FX pair missing');
        Setting::set(
            'whatsapp_self_bank_transfer_fee_enabled',
            $request->boolean('whatsapp_self_bank_transfer_fee_enabled'),
            'boolean',
            'whatsapp',
            'Charge fee when user sends to their own bank account (name or fintech phone match)'
        );
        Setting::set(
            'whatsapp_self_bank_transfer_fee_percent',
            $validated['whatsapp_self_bank_transfer_fee_percent'] ?? config('whatsapp.self_bank_transfer_fee_percent', 1.5),
            'float',
            'whatsapp',
            'Self bank transfer fee percent (deducted from amount sent; recipient gets remainder)'
        );

        $enabledPayCodeCountries = $request->input('whatsapp_checkout_pay_code_countries', []);
        if (! is_array($enabledPayCodeCountries)) {
            $enabledPayCodeCountries = [];
        }
        $enabledPayCodeCountries = array_values(array_unique(array_filter(array_map(
            static fn ($cc) => strtoupper(substr(preg_replace('/\s+/', '', (string) $cc), 0, 2)),
            $enabledPayCodeCountries
        ), static fn (string $cc): bool => strlen($cc) === 2)));
        Setting::set(
            'whatsapp_checkout_pay_code_enabled_countries',
            $enabledPayCodeCountries,
            'json',
            'whatsapp',
            'ISO country codes allowed to use Checkout WhatsApp Pay Code (customer-initiated checkout)'
        );

        $this->saveWhatsappRegionOverrides($request);

        WhatsappCrossBorderP2pFxService::forgetRatesCache();

        return redirect()->route('admin.whatsapp-wallet.settings')
            ->with('success', 'WhatsApp wallet settings saved.');
    }

    private function countryFromPhoneE164(string $phoneE164): string
    {
        $d = preg_replace('/\D+/', '', $phoneE164) ?? '';
        $rows = WhatsappWalletRegionConfig::countryByDial();
        if (! is_array($rows) || $rows === []) {
            return 'Other';
        }
        usort($rows, static fn ($a, $b): int => strlen((string) ($b['dial'] ?? '')) <=> strlen((string) ($a['dial'] ?? '')));
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dial = (string) ($row['dial'] ?? '');
            if ($dial !== '' && str_starts_with($d, $dial)) {
                return (string) ($row['label'] ?? $row['country'] ?? 'Unknown');
            }
        }

        return 'Other';
    }

    /**
     * Persist admin-only dial map and Evolution instance rows (merge with file config at runtime).
     */
    private function saveWhatsappRegionOverrides(Request $request): void
    {
        $dialRows = $request->input('country_dial_extra', []);
        $dialClean = [];
        if (is_array($dialRows)) {
            foreach ($dialRows as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $d = preg_replace('/\D+/', '', (string) ($r['dial'] ?? '')) ?? '';
                $cc = strtoupper(substr(preg_replace('/\s+/', '', (string) ($r['country'] ?? '')), 0, 2));
                if ($d === '' || strlen($cc) !== 2) {
                    continue;
                }
                $cur = strtoupper(substr(preg_replace('/\s+/', '', (string) ($r['currency'] ?? 'USD')), 0, 3));
                if (strlen($cur) !== 3) {
                    $cur = 'USD';
                }
                $label = trim((string) ($r['label'] ?? ''));
                if ($label === '') {
                    $label = $cc;
                }
                $dialClean[] = [
                    'dial' => $d,
                    'country' => $cc,
                    'currency' => $cur,
                    'label' => $label,
                ];
            }
        }
        Setting::set(
            'whatsapp_country_dial_extra',
            $dialClean,
            'json',
            'whatsapp',
            'Admin extra country dial map (merges with config/whatsapp_wallet_regions.php; same dial replaces file entry)'
        );

        $instRows = $request->input('instances_extra', []);
        $out = [];
        if (is_array($instRows)) {
            foreach ($instRows as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $name = trim((string) ($r['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $cc = strtoupper(substr(preg_replace('/\s+/', '', (string) ($r['country'] ?? '')), 0, 2));
                if (strlen($cc) !== 2) {
                    continue;
                }
                $cur = strtoupper(substr(preg_replace('/\s+/', '', (string) ($r['currency'] ?? 'USD')), 0, 3));
                if (strlen($cur) !== 3) {
                    $cur = 'USD';
                }
                $label = trim((string) ($r['label'] ?? ''));
                if ($label === '') {
                    $label = $name;
                }
                $out[$name] = [
                    'country' => $cc,
                    'currency' => $cur,
                    'label' => $label,
                    'features' => [
                        'p2p' => ! empty($r['feature_p2p']),
                        'bank' => ! empty($r['feature_bank']),
                        'vtu' => ! empty($r['feature_vtu']),
                        'rentals' => ! empty($r['feature_rentals']),
                    ],
                ];
            }
        }
        Setting::set(
            'whatsapp_wallet_instances_extra',
            $out,
            'json',
            'whatsapp',
            'Admin extra Evolution instance → country (merges with config; same name replaces / extends file entry)'
        );
    }
}
