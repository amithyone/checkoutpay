<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZapierLog;
use Illuminate\Http\Request;

class ZapierLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ZapierLog::query()->latest();

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by sender email or sender name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('extracted_from_email', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%")
                  ->orWhere('email_content', 'like', "%{$search}%");
            });
        }

        $zapierLogs = $query->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total' => ZapierLog::count(),
            'received' => ZapierLog::where('status', 'received')->count(),
            'processed' => ZapierLog::where('status', 'processed')->count(),
            'matched' => ZapierLog::where('status', 'matched')->count(),
            'rejected' => ZapierLog::where('status', 'rejected')->count(),
            'error' => ZapierLog::where('status', 'error')->count(),
            'today' => ZapierLog::whereDate('created_at', today())->count(),
        ];

        return view('admin.zapier-logs.index', compact('zapierLogs', 'stats'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ZapierLog $zapierLog)
    {
        $zapierLog->load('processedEmail', 'payment');
        return view('admin.zapier-logs.show', compact('zapierLog'));
    }

    /**
     * Retry processing a Zapier log (for error/rejected/no_match status)
     */
    public function retry(ZapierLog $zapierLog)
    {
        try {
            // Only allow retry for error, rejected, no_match, or processed status
            if (!in_array($zapierLog->status, ['error', 'rejected', 'no_match', 'processed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot retry log with status: ' . $zapierLog->status,
                ], 400);
            }

            // Get payload from Zapier log
            $payload = $zapierLog->payload;
            
            if (empty($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payload found in Zapier log',
                ], 400);
            }

            // Create a new request with the payload data
            $request = new \Illuminate\Http\Request($payload);
            $webhookSecret = \App\Models\Setting::get('zapier_webhook_secret', '');
            if ($webhookSecret) {
                $request->headers->set('X-Zapier-Secret', $webhookSecret);
            }

            // Process using the webhook controller
            $webhookController = new \App\Http\Controllers\Api\EmailWebhookController();
            $result = $webhookController->receive($request);

            // Get response data
            $responseData = json_decode($result->getContent(), true);

            return response()->json([
                'success' => true,
                'message' => 'Zapier log reprocessed successfully',
                'result' => $responseData,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error retrying Zapier log', [
                'zapier_log_id' => $zapierLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrying Zapier log: ' . $e->getMessage(),
            ], 500);
        }
    }
}
