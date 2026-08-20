<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\EmailTemplateRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityPurgeEmailTemplateArtifacts extends Command
{
    protected $signature = 'security:purge-email-template-artifacts';

    protected $description = 'Delete temp_email_templates views and report settings with forbidden Blade/PHP syntax';

    public function handle(): int
    {
        $tempDir = resource_path('views/temp_email_templates');
        if (File::isDirectory($tempDir)) {
            File::deleteDirectory($tempDir);
            $this->info("Deleted {$tempDir}");
        } else {
            $this->line('No temp_email_templates directory present.');
        }

        $rows = Setting::query()
            ->where('key', 'like', 'email_template_%_content')
            ->get(['key', 'value']);

        $flagged = 0;
        foreach ($rows as $row) {
            $value = (string) ($row->value ?? '');
            if ($value !== '' && EmailTemplateRenderer::containsForbiddenSyntax($value)) {
                $flagged++;
                $this->warn("Forbidden syntax in settings.key={$row->key}");
            }
        }

        if ($flagged === 0) {
            $this->info('No custom template content with forbidden syntax found.');
        } else {
            $this->warn("Found {$flagged} poisoned template content key(s). Disable/reset them in admin or run Phase 0 SQL.");
        }

        return self::SUCCESS;
    }
}
