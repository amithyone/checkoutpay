<?php

namespace App\Services\MevonPay;

use App\Models\Admin;
use App\Models\Setting;
use Carbon\Carbon;
use RuntimeException;

final class MevonPayReconBaselineService
{
    public const GROUP = 'mevon_recon';

    public const KEY_BASELINE_AT = 'mevon_recon_baseline_at';

    public const KEY_OPENING_BALANCE = 'mevon_recon_opening_balance';

    public const KEY_OPENING_LEDGER = 'mevon_recon_opening_ledger';

    public const KEY_STARTED_BY_ADMIN_ID = 'mevon_recon_started_by_admin_id';

    public function __construct(
        private MevonPayBalanceSnapshotService $balanceSnapshot,
    ) {}

    public function isActive(): bool
    {
        return $this->baselineAt() !== null;
    }

    public function baselineAt(): ?Carbon
    {
        $raw = Setting::get(self::KEY_BASELINE_AT);

        if ($raw === null || $raw === '') {
            return null;
        }

        return Carbon::parse((string) $raw);
    }

    public function openingBalance(): float
    {
        $raw = Setting::get(self::KEY_OPENING_BALANCE);

        return ($raw !== null && $raw !== '' && is_numeric($raw)) ? round((float) $raw, 2) : 0.0;
    }

    public function openingLedger(): ?float
    {
        $raw = Setting::get(self::KEY_OPENING_LEDGER);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    public function startedByAdminId(): ?int
    {
        $raw = Setting::get(self::KEY_STARTED_BY_ADMIN_ID);

        return ($raw !== null && $raw !== '' && is_numeric($raw)) ? (int) $raw : null;
    }

    public function startedByAdmin(): ?Admin
    {
        $id = $this->startedByAdminId();

        return $id !== null ? Admin::query()->find($id) : null;
    }

    /**
     * @return array{
     *   baseline_at: string,
     *   opening_balance: float,
     *   opening_ledger: ?float,
     *   started_by_admin_id: int,
     *   balance_ok: bool,
     *   balance_message: string
     * }
     */
    public function initialize(Admin $admin): array
    {
        if ($this->isActive()) {
            throw new RuntimeException('Mevon balance monitoring is already active. Super admins can reset the baseline.');
        }

        return $this->captureAndStore($admin);
    }

    /**
     * @return array{
     *   baseline_at: string,
     *   opening_balance: float,
     *   opening_ledger: ?float,
     *   started_by_admin_id: int,
     *   balance_ok: bool,
     *   balance_message: string
     * }
     */
    public function reset(Admin $admin): array
    {
        if (! $admin->isSuperAdmin()) {
            throw new RuntimeException('Only super admins can reset the Mevon balance monitoring baseline.');
        }

        return $this->captureAndStore($admin);
    }

    /**
     * @return array{
     *   baseline_at: string,
     *   opening_balance: float,
     *   opening_ledger: ?float,
     *   started_by_admin_id: int,
     *   balance_ok: bool,
     *   balance_message: string
     * }
     */
    private function captureAndStore(Admin $admin): array
    {
        $live = $this->balanceSnapshot->forDashboard();

        if (! ($live['ok'] ?? false)) {
            throw new RuntimeException((string) ($live['message'] ?? 'Could not fetch live Mevon balance.'));
        }

        $openingBalance = $live['naira_balance'] ?? null;
        if ($openingBalance === null) {
            throw new RuntimeException('Mevon balance API did not return naira_balance.');
        }

        $now = now();
        $openingLedger = $live['naira_ledger'] ?? null;

        Setting::set(self::KEY_BASELINE_AT, $now->toIso8601String(), 'string', self::GROUP, 'When Mevon balance monitoring started');
        Setting::set(self::KEY_OPENING_BALANCE, round((float) $openingBalance, 2), 'float', self::GROUP, 'Live naira balance at monitoring start');
        Setting::set(
            self::KEY_OPENING_LEDGER,
            $openingLedger !== null ? round((float) $openingLedger, 2) : '',
            'float',
            self::GROUP,
            'Live naira ledger at monitoring start (cross-check)'
        );
        Setting::set(self::KEY_STARTED_BY_ADMIN_ID, $admin->id, 'integer', self::GROUP, 'Admin who started monitoring');

        return [
            'baseline_at' => $now->toIso8601String(),
            'opening_balance' => round((float) $openingBalance, 2),
            'opening_ledger' => $openingLedger !== null ? round((float) $openingLedger, 2) : null,
            'started_by_admin_id' => (int) $admin->id,
            'balance_ok' => true,
            'balance_message' => (string) ($live['message'] ?? 'OK'),
        ];
    }

    /**
     * @return array{
     *   active: bool,
     *   baseline_at: ?string,
     *   opening_balance: float,
     *   opening_ledger: ?float,
     *   started_by_admin_id: ?int,
     *   started_by_admin_name: ?string
     * }
     */
    public function info(): array
    {
        $admin = $this->startedByAdmin();

        return [
            'active' => $this->isActive(),
            'baseline_at' => $this->baselineAt()?->toIso8601String(),
            'opening_balance' => $this->openingBalance(),
            'opening_ledger' => $this->openingLedger(),
            'started_by_admin_id' => $this->startedByAdminId(),
            'started_by_admin_name' => $admin?->name,
        ];
    }
}
