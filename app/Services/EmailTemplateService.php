<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\File;

class EmailTemplateService
{
    /**
     * Get email template content (custom from DB or default from Blade file)
     */
    public static function getTemplateContent(string $templateKey): ?string
    {
        // Check if custom template is enabled
        $isCustom = Setting::get("email_template_{$templateKey}_custom", false);
        
        if ($isCustom) {
            $customContent = Setting::get("email_template_{$templateKey}_content", null);
            if ($customContent) {
                return $customContent;
            }
        }
        
        // Fallback to default Blade file
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
        return Setting::get("email_template_{$templateKey}_custom", false);
    }

    /**
     * Render email template (custom or default)
     * Returns the rendered HTML string
     */
    public static function renderTemplate(string $templateKey, array $data, string $defaultView): string
    {
        $customContent = self::getTemplateContent($templateKey);
        
        if ($customContent) {
            // Render custom template from database
            try {
                // Store custom template in a temporary location for Blade to compile
                $tempDir = resource_path('views/temp_email_templates');
                if (!File::exists($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }
                
                $tempViewPath = $tempDir . "/{$templateKey}.blade.php";
                File::put($tempViewPath, $customContent);
                
                // Render using Laravel's view system
                $rendered = view("temp_email_templates.{$templateKey}", $data)->render();
                
                return $rendered;
            } catch (\Exception $e) {
                // If custom template fails, fall back to default
                \Log::error("Failed to render custom email template {$templateKey}: " . $e->getMessage());
                return view($defaultView, $data)->render();
            }
        }
        
        // Use default Blade view
        return view($defaultView, $data)->render();
    }
}
