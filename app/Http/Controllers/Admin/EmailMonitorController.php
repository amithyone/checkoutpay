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
            
            return response()->json([
                'success' => true,
                'message' => 'Email fetching (IMAP) completed successfully',
                'output' => $output,
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
            // Run the direct filesystem email reading command
            Artisan::call('payment:read-emails-direct', ['--all' => true]);
            $output = Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Direct filesystem email reading completed successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            Log::error('Error reading emails directly from filesystem', [
                'error' => $e->getMessage(),
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
