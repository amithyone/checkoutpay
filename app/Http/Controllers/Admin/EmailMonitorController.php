<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class EmailMonitorController extends Controller
{
    /**
     * Manually trigger email monitoring (IMAP)
     */
    public function fetchEmails(Request $request)
    {
        try {
            // Check if IMAP is disabled
            $disableImap = \App\Models\Setting::get('disable_imap_fetching', false);
            
            if ($disableImap) {
                return response()->json([
                    'success' => false,
                    'message' => 'IMAP fetching is disabled. Use "Fetch Emails (Direct)" instead.',
                ], 400);
            }

            // Run the email monitoring command (IMAP)
            Artisan::call('payment:monitor-emails', ['--all' => true]);
            $output = Artisan::output();
            
            // Extract summary from output (last few lines with totals)
            $outputLines = explode("\n", trim($output));
            $summaryLines = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $totalFailed = 0;
            
            // Look for summary lines (contain totals)
            foreach ($outputLines as $line) {
                if (preg_match('/Total processed:\s*(\d+)/i', $line, $matches)) {
                    $totalProcessed = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/Total skipped:\s*(\d+)/i', $line, $matches)) {
                    $totalSkipped = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/Total failed:\s*(\d+)/i', $line, $matches)) {
                    $totalFailed = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/✅|⏭️|❌|📧|ℹ️/', $line)) {
                    // Include lines with emoji indicators (summary lines)
                    $summaryLines[] = $line;
                }
            }
            
            // Get last 20 lines as summary (usually contains the totals)
            $lastLines = array_slice($outputLines, -20);
            $summary = implode("\n", array_unique(array_merge($summaryLines, $lastLines)));
            
            // Truncate full output to prevent "output too large" error (max 5000 chars)
            $truncatedOutput = strlen($output) > 5000 
                ? substr($output, 0, 5000) . "\n\n... (output truncated, showing summary only) ..."
                : $output;
            
            return response()->json([
                'success' => true,
                'message' => 'Email fetching (IMAP) completed successfully',
                'summary' => $summary ?: 'No summary available',
                'stats' => [
                    'processed' => $totalProcessed,
                    'skipped' => $totalSkipped,
                    'failed' => $totalFailed,
                ],
                'output' => $truncatedOutput, // Truncated output for debugging
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching emails manually (IMAP)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching emails: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger direct filesystem email reading
     */
    public function fetchEmailsDirect(Request $request)
    {
        try {
            // Increase memory and time limits for email processing
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 minutes
            
            // Run the direct filesystem email reading command
            try {
                Artisan::call('payment:read-emails-direct', ['--all' => true]);
                $output = Artisan::output();
            } catch (\Throwable $e) {
                Log::error('Error running read-emails-direct command', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error running email command: ' . $e->getMessage(),
                    'stats' => [
                        'processed' => 0,
                        'skipped' => 0,
                        'failed' => 0,
                    ],
                ], 200); // Return 200 to prevent cron failures
            }
            
            // Extract summary from output (last few lines with totals)
            $outputLines = explode("\n", trim($output));
            $summaryLines = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $totalFailed = 0;
            
            // Look for summary lines (contain totals)
            foreach ($outputLines as $line) {
                if (preg_match('/Total processed:\s*(\d+)/i', $line, $matches)) {
                    $totalProcessed = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/Total skipped:\s*(\d+)/i', $line, $matches)) {
                    $totalSkipped = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/Total failed:\s*(\d+)/i', $line, $matches)) {
                    $totalFailed = (int)$matches[1];
                    $summaryLines[] = $line;
                } elseif (preg_match('/✅|⏭️|❌|📧|ℹ️/', $line)) {
                    // Include lines with emoji indicators (summary lines)
                    $summaryLines[] = $line;
                }
            }
            
            // Get last 20 lines as summary (usually contains the totals)
            $lastLines = array_slice($outputLines, -20);
            $summary = implode("\n", array_unique(array_merge($summaryLines, $lastLines)));
            
            // Truncate full output to prevent "output too large" error (max 5000 chars)
            $truncatedOutput = strlen($output) > 5000 
                ? substr($output, 0, 5000) . "\n\n... (output truncated, showing summary only) ..."
                : $output;
            
            return response()->json([
                'success' => true,
                'message' => 'Direct filesystem email reading completed successfully',
                'summary' => $summary ?: 'No summary available',
                'stats' => [
                    'processed' => $totalProcessed,
                    'skipped' => $totalSkipped,
                    'failed' => $totalFailed,
                ],
                'output' => $truncatedOutput, // Truncated output for debugging
            ]);
        } catch (\Throwable $e) {
            Log::error('Error reading emails directly from filesystem', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error reading emails: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for transaction updates (re-check stored emails against pending payments)
     */
    public function checkTransactionUpdates(Request $request)
    {
        try {
            // Re-extract and match stored emails
            Artisan::call('emails:re-extract', ['--all' => true]);
            $reExtractOutput = Artisan::output();
            
            // Then run email monitoring to fetch new emails
            Artisan::call('payment:monitor-emails');
            $monitorOutput = Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Transaction update check completed',
                're_extract_output' => $reExtractOutput,
                'monitor_output' => $monitorOutput,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking transaction updates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking transaction updates: ' . $e->getMessage(),
            ], 500);
        }
    }
}
