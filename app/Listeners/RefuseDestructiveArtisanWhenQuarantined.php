<?php

namespace App\Listeners;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Output\OutputInterface;

class RefuseDestructiveArtisanWhenQuarantined
{
    public function __construct(private QuarantineService $quarantine) {}

    public function handle(CommandStarting $event): void
    {
        $name = (string) $event->command;
        if ($name === '' || ! $this->quarantine->shouldBlockArtisan($name)) {
            return;
        }

        $this->abort($event->output, $name);
    }

    private function abort(OutputInterface $output, string $name): void
    {
        $status = $this->quarantine->status();
        $reasons = implode(', ', $status['reasons'] ?: ['fingerprint_or_lock']);

        $output->writeln('');
        $output->writeln('<error>QUARANTINE: Refusing '.$name.'</error>');
        $output->writeln('Do not migrate against a hijacked or empty database.');
        $output->writeln('Check DB_HOST / DB_DATABASE in .error, then: php artisan quarantine:status');
        $output->writeln('Clear with: php artisan quarantine:clear --code=YOUR_CODE');
        $output->writeln('Reasons: '.$reasons);
        $output->writeln('');

        // Hard stop before the command runs
        exit(1);
    }
}
