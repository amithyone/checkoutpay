<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PythonExtractionService
{
    protected string $baseUrl;
    protected int $timeout;
    protected float $minConfidence;

    public function __construct()
    {
        $this->baseUrl = config('services.python_extractor.url', 'http://localhost:8000');
        $this->timeout = config('services.python_extractor.timeout', 10);
        $this->minConfidence = config('services.python_extractor.min_confidence', 0.7);
    }

    /**
     * Extract payment information from email content using Python service.
     *
     * @param array $emailData
     * @return array|null Returns ['data' => [...], 'method' => '...', 'confidence' => 0.95] or null on failure
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/extract", [
                    'email_id' => $emailData['processed_email_id'] ?? 0,
                    'subject' => $emailData['subject'] ?? '',
                    'from_email' => $emailData['from'] ?? '',
                    'text_body' => $emailData['text'] ?? '',
                    'html_body' => $emailData['html'] ?? '',
                    'email_date' => $emailData['date'] ?? null,
                ]);

            if (!$response->successful()) {
                Log::error('Python extraction service returned error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                return null;
            }

            $result = $response->json();

            if (!$result['success'] || !isset($result['data'])) {
                Log::info('Python extraction service returned no data', [
                    'success' => $result['success'] ?? false,
                    'errors' => $result['errors'] ?? [],
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                return null;
            }

            $data = $result['data'];

            // Validate confidence score
            $confidence = $data['confidence'] ?? 0.0;
            if ($confidence < $this->minConfidence) {
                Log::warning('Python extraction returned low confidence score', [
                    'confidence' => $confidence,
                    'min_required' => $this->minConfidence,
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                return null;
            }

            // Validate amount (must be >= 10 Naira)
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount < 10) {
                Log::warning('Python extraction returned invalid amount', [
                    'amount' => $amount,
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                return null;
            }

            // Map Python response to Laravel format
            return [
                'data' => [
                    'amount' => $amount,
                    'sender_name' => $data['sender_name'] ?? null,
                    'account_number' => $data['account_number'] ?? null,
                    'currency' => $data['currency'] ?? 'NGN',
                    'direction' => $data['direction'] ?? 'credit',
                    'email_subject' => $emailData['subject'] ?? '',
                    'email_from' => $emailData['from'] ?? '',
                    'extracted_at' => now()->toISOString(),
                ],
                'method' => $data['source'] ?? 'python_extractor',
                'confidence' => $confidence,
                'diagnostics' => $result['diagnostics'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Error calling Python extraction service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
    }

    /**
     * Check if Python extraction service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return Cache::remember('python_extractor_health', 60, function () {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/health");
                return $response->successful();
            } catch (\Exception $e) {
                Log::warning('Python extraction service health check failed', [
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        });
    }
}
