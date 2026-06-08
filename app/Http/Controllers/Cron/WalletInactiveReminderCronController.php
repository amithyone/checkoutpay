<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWalletInactiveReminder;
use App\Services\Consumer\ConsumerWalletInactiveReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletInactiveReminderCronController extends Controller
{
    public function sendMorning(Request $request, ConsumerWalletInactiveReminderService $reminders): JsonResponse
    {
        return $this->run($request, $reminders, WhatsappWalletInactiveReminder::SLOT_MORNING);
    }

    public function sendEvening(Request $request, ConsumerWalletInactiveReminderService $reminders): JsonResponse
    {
        return $this->run($request, $reminders, WhatsappWalletInactiveReminder::SLOT_EVENING);
    }

    private function run(Request $request, ConsumerWalletInactiveReminderService $reminders, string $slot): JsonResponse
    {
        $requiredToken = env('CRON_EMAIL_FETCH_TOKEN');
        if ($requiredToken) {
            $providedToken = $request->query('token') ?? $request->header('X-Cron-Token');
            if ($providedToken !== $requiredToken) {
                Log::warning('Unauthorized cron access attempt (wallet inactive reminders)', [
                    'ip' => $request->ip(),
                    'slot' => $slot,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid or missing token',
                    'timestamp' => now()->toDateTimeString(),
                ], 401);
            }
        }

        $start = microtime(true);
        try {
            $stats = $reminders->sendForSlot($slot);
        } catch (\Throwable $e) {
            Log::error('Wallet inactive reminder cron failed', [
                'slot' => $slot,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
                'slot' => $slot,
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inactive wallet reminders processed',
            'slot' => $slot,
            'wallets' => $stats['wallets'],
            'push_sent' => $stats['push'],
            'whatsapp_sent' => $stats['whatsapp'],
            'skipped' => $stats['skipped'],
            'execution_time_seconds' => round(microtime(true) - $start, 2),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
