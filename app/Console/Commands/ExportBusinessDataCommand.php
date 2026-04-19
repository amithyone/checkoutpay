<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BusinessDataExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExportBusinessDataCommand extends Command
{
    protected $signature = 'business:export-data
                            {--user-id= : users.id — exports the business linked via users.business_id}
                            {--business-id= : businesses.id — export this business directly (overrides user-id)}
                            {--output= : Full path to JSON file (optional)}';

    protected $description = 'Export one business and related rows (websites, payments, withdrawals, etc.) to JSON for migration to another environment.';

    public function handle(BusinessDataExportService $exporter): int
    {
        $businessId = $this->option('business-id') ? (int) $this->option('business-id') : null;
        $sourceUserId = null;

        if (! $businessId && $this->option('user-id')) {
            $sourceUserId = (int) $this->option('user-id');
            if (! Schema::hasTable('users')) {
                $this->warn('Table `users` does not exist on this database. If you meant the merchant account primary key, use --business-id='.$sourceUserId.' instead.');
                $this->error('Cannot resolve --user-id without a users table.');

                return self::FAILURE;
            }
            $user = User::find($sourceUserId);
            if (! $user) {
                $this->error("No user found with id {$sourceUserId}.");

                return self::FAILURE;
            }
            if (! $user->business_id) {
                $this->error("User {$sourceUserId} has no business_id. Link the user to a business in the database, or pass --business-id=.");

                return self::FAILURE;
            }
            $businessId = (int) $user->business_id;
            $this->info("Resolved user {$sourceUserId} → business_id {$businessId}.");
        }

        if (! $businessId) {
            $this->error('Provide --user-id= or --business-id=.');

            return self::FAILURE;
        }

        try {
            $path = $this->option('output') ?: null;
            $file = $exporter->writeJsonFile($businessId, $sourceUserId, $path ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Export written to: '.$file);
        $this->warn('File may contain API keys, hashed passwords, and KYC data. Keep it private.');

        return self::SUCCESS;
    }
}
