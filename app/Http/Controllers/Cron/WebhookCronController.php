<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookCronController extends Controller
{
    /**
     * Process pending webhooks via cron
     * This endpoint can be called by external cron services
     * 
     * URL: /cron/process-webhooks?secret=YOUR_SECRET
     */
    public function processWebhooks(Request $request)
    {
        // Verify secret token for security
        $secret = $request->query('secret') ?? $request->header('X-Cron-Secret');
        $expectedSecret = env('WEBHOOK_CRON_SECRET', 'change-me-in-production-' . md5(config('app.key')));
        
        if (empty($secret) || $secret !== $expectedSecret) {
            Log::warning('Unauthorized webhook cron request', [
                'ip' => $request->ip(),
                'has_secret' => !empty($secret),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing secret',
            ], 401);
        }

        try {
            // Get pending/failed webhooks that need to be sent
            $limit = min((int) ($request->query('limit', 50)), 100); // Max 100 per run
            
            $payments = Payment::where('status', Payment::STATUS_APPROVED)
                ->where(function ($query) {
                    $query->whereNull('webhook_status')
                        ->orWhere('webhook_status', 'pending')
                        ->orWhere('webhook_status', 'failed');
                })
                ->where(function ($query) {
                    // Only process payments that haven't been sent recently (avoid spam)
                    $query->whereNull('webhook_sent_at')
                        ->orWhere('webhook_sent_at', '<', now()->subMinutes(5)); // Retry after 5 minutes
                })
                ->where(function ($query) {
                    // Limit retry attempts
                    $query->whereNull('webhook_attempts')
                        ->orWhere('webhook_attempts', '<', 5);
                })
                ->orderBy('matched_at', 'asc') // Process oldest first
                ->limit($limit)
                ->get();

            $processed = 0;
            $queued = 0;
            $errors = [];

            foreach ($payments as $payment) {
                try {
                    // Dispatch webhook job to queue
                    SendWebhookNotification::dispatch($payment);
                    $queued++;
                    $processed++;
                    
                    Log::info('Queued webhook for processing via cron', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Payment {$payment->id}: {$e->getMessage()}";
                    Log::error('Failed to queue webhook via cron', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Processed {$processed} webhook(s)",
                'queued' => $queued,
                'errors' => $errors,
                'total_found' => $payments->count(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing webhooks via cron', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing webhooks: ' . $e->getMessage(),
            ], 500);
        }
    }
}
