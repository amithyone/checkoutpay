<?php

namespace App\Console\Commands;

use App\Services\WalletImport\FormCsvWalletSterilizerService;
use Illuminate\Console\Command;

class SterilizeFormCsvWalletsCommand extends Command
{
    protected $signature = 'wallet:sterilize-form-csv
        {csv? : Path to Form Responses CSV}
        {--out= : Output directory (default database/backups/imports/sterilized)}
        {--skip-name-enquiry : Do not call bank name enquiry (CSV names only)}
        {--limit=0 : Process only first N data rows (0 = all)}';

    protected $description = 'Sterilize Form Responses CSV: normalize phones/banks/BVN and confirm names via bank name enquiry';

    public function handle(FormCsvWalletSterilizerService $sterilizer): int
    {
        $csv = $this->argument('csv')
            ?: base_path('database/backups/imports/use data - Form Responses 1.csv');
        $outDir = $this->option('out')
            ?: base_path('database/backups/imports/sterilized');

        if (! is_readable($csv)) {
            $this->error('CSV not readable: '.$csv);

            return self::FAILURE;
        }

        $this->info('Reading: '.$csv);
        $skipNe = (bool) $this->option('skip-name-enquiry');
        $limit = max(0, (int) $this->option('limit'));
        if ($skipNe) {
            $this->warn('Skipping name enquiry — names will come from CSV only.');
        } else {
            $this->info('Name enquiry: primary bank code only (progress every 25 rows).');
        }

        $barTotal = $limit > 0 ? $limit : 849;
        $bar = $this->output->createProgressBar($barTotal);
        $bar->start();

        $result = $sterilizer->sterilizeFile(
            $csv,
            $skipNe,
            $limit,
            function (int $done, int $approxTotal, array $record) use ($bar): void {
                $bar->setProgress(min($done, $bar->getMaxSteps()));
                if ($done % 25 === 0) {
                    $status = (string) ($record['status'] ?? '');
                    $src = (string) ($record['name_source'] ?? '');
                    $this->newLine();
                    $this->line("  … {$done} rows (last: {$status}, name={$src})");
                }
            }
        );
        $bar->finish();
        $this->newLine(2);

        $rows = $result['rows'];
        if ($limit > 0) {
            $this->warn("Limited to first {$limit} data row(s).");
        }

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $jsonl = rtrim($outDir, '/').'/form-responses-sterilized.jsonl';
        $report = rtrim($outDir, '/').'/form-responses-report.csv';
        $summaryPath = rtrim($outDir, '/').'/form-responses-summary.json';

        $sterilizer->writeJsonl($jsonl, $rows);
        $sterilizer->writeReportCsv($report, $rows);
        file_put_contents(
            $summaryPath,
            json_encode($result['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->table(
            ['metric', 'count'],
            collect($result['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        $this->info('Wrote: '.$jsonl);
        $this->info('Wrote: '.$report);
        $this->info('Wrote: '.$summaryPath);
        $this->line('Next: review report, then php artisan wallet:seed-form-sterilized --dry-run');

        return self::SUCCESS;
    }
}
