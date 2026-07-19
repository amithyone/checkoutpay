<?php

namespace App\Console\Commands;

use App\Services\Consumer\WalletReferralLeaderboardService;
use Illuminate\Console\Command;

class SettleReferralLeaderboardCommand extends Command
{
    protected $signature = 'wallet:referral-leaderboard {--month= : YYYY-MM to settle (default: previous calendar month Africa/Lagos)}';

    protected $description = 'Settle monthly wallet referral leaderboard prizes from the admin-configured pot';

    public function handle(WalletReferralLeaderboardService $leaderboard): int
    {
        $month = $this->option('month');
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;

        $result = $leaderboard->settleMonth($month);
        $this->info(($result['message'] ?? 'Done').' paid='.($result['paid'] ?? 0));

        return self::SUCCESS;
    }
}
