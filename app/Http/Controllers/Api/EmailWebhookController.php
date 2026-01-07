<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmailPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    /**
     * Receive email via webhook (from email forwarding service)
     * 
     * This endpoint receives emails forwarded from Gmail via services like:
     - Zapier
     - Make.com
     - Email Parser
     - Custom email-to-webhook service
     */
    public function receive(Request $request)
    {
        try {
            // Log incoming webhook
            Log::info('Email webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            // Parse email data from webhook
            // Different services send different formats, so we handle multiple
            $emailData = $this->parseWebhookData($request);

            if (empty($emailData['subject']) || empty($emailData['from'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required email fields (subject, from)',
                ], 400);
            }

            // Dispatch job to process email (same as IMAP flow)
            ProcessEmailPayment::dispatch($emailData);

            Log::info('Email webhook processed successfully', [
                'subject' => $emailData['subject'],
                'from' => $emailData['from'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email received and queued for processing',
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing email webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse webhook data from different services
     */
    protected function parseWebhookData(Request $request): array
    {
        $data = $request->all();

        // Format 1: Zapier/Make.com format
        if (isset($data['subject']) && isset($data['from'])) {
            return [
                'subject' => $data['subject'] ?? '',
                'from' => $this->extractEmail($data['from'] ?? ''),
                'to' => $this->extractEmail($data['to'] ?? ''),
                'text' => $data['text'] ?? $data['body'] ?? $data['plain'] ?? '',
                'html' => $data['html'] ?? '',
                'date' => $data['date'] ?? now()->toDateTimeString(),
                'source' => 'webhook',
            ];
        }

        // Format 2: Email Parser format
        if (isset($data['email'])) {
            $email = $data['email'];
            return [
                'subject' => $email['subject'] ?? $data['subject'] ?? '',
                'from' => $this->extractEmail($email['from'] ?? $data['from'] ?? ''),
                'to' => $this->extractEmail($email['to'] ?? $data['to'] ?? ''),
                'text' => $email['text'] ?? $email['body'] ?? $data['text'] ?? '',
                'html' => $email['html'] ?? $data['html'] ?? '',
                'date' => $email['date'] ?? $data['date'] ?? now()->toDateTimeString(),
                'source' => 'webhook',
            ];
        }

        // Format 3: Raw email format
        if (isset($data['raw'])) {
            return $this->parseRawEmail($data['raw']);
        }

        // Format 4: Generic format (try to extract from any fields)
        return [
            'subject' => $data['subject'] ?? $data['Subject'] ?? $data['title'] ?? '',
            'from' => $this->extractEmail($data['from'] ?? $data['From'] ?? $data['sender'] ?? ''),
            'to' => $this->extractEmail($data['to'] ?? $data['To'] ?? $data['recipient'] ?? ''),
            'text' => $data['text'] ?? $data['body'] ?? $data['content'] ?? $data['message'] ?? '',
            'html' => $data['html'] ?? $data['HTML'] ?? '',
            'date' => $data['date'] ?? $data['Date'] ?? $data['timestamp'] ?? now()->toDateTimeString(),
            'source' => 'webhook',
        ];
    }

    /**
     * Extract email address from string
     */
    protected function extractEmail(string $string): string
    {
        // Extract email from formats like "Name <email@example.com>" or just "email@example.com"
        if (preg_match('/<(.+?)>/', $string, $matches)) {
            return $matches[1];
        }
        
        // Check if it's already just an email
        if (filter_var($string, FILTER_VALIDATE_EMAIL)) {
            return $string;
        }

        // Try to find email in string
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $string, $matches)) {
            return $matches[0];
        }

        return $string;
    }

    /**
     * Parse raw email content
     */
    protected function parseRawEmail(string $rawEmail): array
    {
        // Simple email parsing (for basic cases)
        // For production, consider using a proper email parser library
        
        $lines = explode("\n", $rawEmail);
        $headers = [];
        $body = [];
        $inBody = false;

        foreach ($lines as $line) {
            if (empty(trim($line)) && !$inBody) {
                $inBody = true;
                continue;
            }

            if (!$inBody) {
                if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                    $headers[strtolower(trim($matches[1]))] = trim($matches[2]);
                }
            } else {
                $body[] = $line;
            }
        }

        return [
            'subject' => $headers['subject'] ?? '',
            'from' => $this->extractEmail($headers['from'] ?? ''),
            'to' => $this->extractEmail($headers['to'] ?? ''),
            'text' => implode("\n", $body),
            'html' => '',
            'date' => $headers['date'] ?? now()->toDateTimeString(),
            'source' => 'webhook',
        ];
    }
}
