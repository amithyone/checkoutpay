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
     * Supports both FastAPI service (HTTP) and simple script (shell_exec) for shared hosting.
     *
     * @param array $emailData
     * @return array|null Returns ['data' => [...], 'method' => '...', 'confidence' => 0.95] or null on failure
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        $mode = config('services.python_extractor.mode', 'http'); // 'http' or 'script'
        
        try {
            if ($mode === 'script') {
                // Use simple Python script (shared hosting compatible)
                return $this->extractViaScript($emailData);
            } else {
                // Use FastAPI HTTP service
                return $this->extractViaHttp($emailData);
            }
        } catch (\Exception $e) {
            Log::error('Python extraction failed', [
                'error' => $e->getMessage(),
                'mode' => $mode,
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
    }
    
    /**
     * Extract via HTTP (FastAPI service).
     */
    protected function extractViaHttp(array $emailData): ?array
    {
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

        // Use processResult to handle validation and formatting (consistent for both modes)
        return $this->processResult($result, $emailData);
    }
    
    /**
     * Extract via Python script (shared hosting compatible - no FastAPI needed).
     */
    protected function extractViaScript(array $emailData): ?array
    {
        $scriptPath = config('services.python_extractor.script_path', base_path('python-extractor/extract_simple.py'));
        $pythonCommand = config('services.python_extractor.python_command', 'python3');
        
        if (!file_exists($scriptPath)) {
            Log::warning('Python extraction script not found', [
                'script_path' => $scriptPath,
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
        
        // Prepare input data
        $inputData = json_encode([
            'email_id' => $emailData['processed_email_id'] ?? 0,
            'subject' => $emailData['subject'] ?? '',
            'from_email' => $emailData['from'] ?? '',
            'text_body' => $emailData['text'] ?? '',
            'html_body' => $emailData['html'] ?? '',
            'email_date' => $emailData['date'] ?? null,
        ]);
        
        // Call Python script
        $command = sprintf(
            '%s %s',
            escapeshellarg($pythonCommand),
            escapeshellarg($scriptPath)
        );
        
        $process = proc_open($command, [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);
        
        if (!is_resource($process)) {
            Log::error('Failed to open Python script process');
            return null;
        }
        
        // Write input data to stdin
        fwrite($pipes[0], $inputData);
        fclose($pipes[0]);
        
        // Read output from stdout
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        // Read errors from stderr
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0 || empty($output)) {
            Log::error('Python script execution failed', [
                'return_code' => $returnCode,
                'errors' => $errors,
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
        
        try {
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Python script returned invalid JSON', [
                    'output' => $output,
                    'json_error' => json_last_error_msg(),
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse Python script output', [
                'error' => $e->getMessage(),
                'output' => $output,
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
        
        return $this->processResult($result, $emailData);
    }
    
    /**
     * Process extraction result (common for both HTTP and script modes).
     */
    protected function processResult(array $result, array $emailData): ?array
    {
        if (!$result['success'] || !isset($result['data'])) {
            Log::info('Python extraction returned no data', [
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
    }
    
    /**
     * Check if Python extraction service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $mode = config('services.python_extractor.mode', 'http');
        
        if ($mode === 'script') {
            // Check if script exists
            $scriptPath = config('services.python_extractor.script_path', base_path('python-extractor/extract_simple.py'));
            $pythonCommand = config('services.python_extractor.python_command', 'python3');
            
            if (!file_exists($scriptPath)) {
                return false;
            }
            
            // Try to execute Python command
            $command = sprintf('%s --version 2>&1', escapeshellarg($pythonCommand));
            $output = shell_exec($command);
            return !empty($output) && strpos($output, 'Python') !== false;
        } else {
            // HTTP mode - check health endpoint
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
}