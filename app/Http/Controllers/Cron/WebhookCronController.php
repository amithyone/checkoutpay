<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookNotification;
use App\Services\PendingWebhookDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookCronController extends Controller
{
    /**
     * Process pending webhooks via cron
     * This endpoint can be called by external cron services
     *
     * URL: /api/v1/cron/process-webhooks
     * Public endpoint - no authentication required
     *
     * Query:
     * - limit: batch size (default 50, max 100) when all=0
     * - all=1: repeat batches until empty or max reached
     * - max: with all=1, cap total payments (default 10000, max 20000)
     * - force=1: ignore 5-minute webhook_sent_at retry cooldown
     */
    public function processWebhooks(Request $request, PendingWebhookDispatchService $dispatcher)
    {
        try {
            $force = $request->boolean('force');
            $all = $request->boolean('all');

            if ($all) {
                $batch = max(1, min(500, (int) $request->query('batch', 100)));
                $max = max(1, min(20000, (int) $request->query('max', 10000)));
                $result = $dispatcher->processUntilExhausted($batch, $max, $force);

                Log::info('Webhook cron processed (all mode)', $result);

                return response()->json([
                    'success' => true,
                    'message' => "Processed {$result['sent']} webhook(s)",
                    'sent' => $result['sent'],
                    'errors' => $result['errors'],
                    'batches' => $result['batches'],
                    'pending_after' => $result['pending_after'],
                    'timestamp' => now()->toISOString(),
                ]);
            }

            $limit = min((int) $request->query('limit', 50), 100);

            $payments = $dispatcher->collectPending($limit, $force);
            $errors = [];
            $processed = 0;

            foreach ($payments as $payment) {
                try {
                    SendWebhookNotification::dispatchSync($payment);
                    $processed++;

                    Log::info('Webhook sent via cron (sync)', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Payment {$payment->id}: {$e->getMessage()}";
                    Log::error('Failed to send webhook via cron', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Processed {$processed} webhook(s)",
                'sent' => $processed,
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
