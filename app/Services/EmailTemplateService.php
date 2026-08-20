<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class EmailTemplateService
{
    /**
     * Get email template content (custom from DB or default from Blade file)
     */
    public static function getTemplateContent(string $templateKey): ?string
    {
        $isCustom = Setting::get("email_template_{$templateKey}_custom", false);

        if ($isCustom) {
            $customContent = Setting::get("email_template_{$templateKey}_content", null);
            if ($customContent) {
                return $customContent;
            }
        }

        return null;
    }

    /**
     * Get email template subject (custom from DB or default)
     */
    public static function getTemplateSubject(string $templateKey, string $defaultSubject): string
    {
        $customSubject = Setting::get("email_template_{$templateKey}_subject", null);

        return $customSubject ?: $defaultSubject;
    }

    /**
     * Check if custom template is enabled
     */
    public static function isCustomTemplate(string $templateKey): bool
    {
        return (bool) Setting::get("email_template_{$templateKey}_custom", false);
    }

    /**
     * Render email template (custom or default).
     * Custom templates use safe {{ $variable }} substitution only — never Blade/PHP execution.
     *
     * @param  array<string, mixed>  $data
     */
    public static function renderTemplate(string $templateKey, array $data, string $defaultView): string
    {
        $customContent = self::getTemplateContent($templateKey);

        if ($customContent) {
            if (EmailTemplateRenderer::containsForbiddenSyntax($customContent)) {
                Log::warning('email_template_forbidden_syntax_fallback', [
                    'template' => $templateKey,
                ]);

                return view($defaultView, $data)->render();
            }

            try {
                return EmailTemplateRenderer::render($customContent, $data);
            } catch (\Throwable $e) {
                Log::error("Failed to render custom email template {$templateKey}: ".$e->getMessage());

                return view($defaultView, $data)->render();
            }
        }

        return view($defaultView, $data)->render();
    }
}
