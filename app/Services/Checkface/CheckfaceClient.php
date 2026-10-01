<?php

namespace App\Services\Checkface;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the CheckFace FastAPI service (enroll / verify / liveness).
 */
class CheckfaceClient
{
    public function isConfigured(): bool
    {
        return (bool) config('checkface.enabled', true)
            && trim((string) config('checkface.base_url')) !== ''
            && trim((string) config('checkface.api_token')) !== '';
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    public function learningStatus(string $userId): array
    {
        return $this->jsonGet('/learning-status', ['user_id' => $userId]);
    }

    /**
     * @param  list<UploadedFile|array{contents: string, filename: string, mime?: string}>  $extraSamples
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    public function enrollFace(string $userId, UploadedFile|array $photo, array $extraSamples = []): array
    {
        $multipart = [
            ['name' => 'user_id', 'contents' => $userId],
            $this->filePart('photo', $photo),
        ];
        foreach ($extraSamples as $sample) {
            $multipart[] = $this->filePart('samples', $sample);
        }

        return $this->multipartPost('/enroll-face', $multipart);
    }

    /**
     * @param  UploadedFile|array{contents: string, filename: string, mime?: string}  $selfie
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    public function verifyFace(string $userId, UploadedFile|array $selfie): array
    {
        return $this->multipartPost('/verify-face', [
            ['name' => 'user_id', 'contents' => $userId],
            $this->filePart('selfie', $selfie),
        ]);
    }

    /**
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    public function createLivenessSession(string $userId): array
    {
        return $this->multipartPost('/liveness/session', [
            ['name' => 'user_id', 'contents' => $userId],
        ]);
    }

    /**
     * @param  UploadedFile|array{contents: string, filename: string, mime?: string}  $clip
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    public function submitLivenessVideo(string $sessionId, UploadedFile|array $clip): array
    {
        return $this->multipartPost('/liveness/video', [
            ['name' => 'session_id', 'contents' => $sessionId],
            $this->filePart('clip', $clip),
        ], (int) config('checkface.video_timeout_seconds', 90));
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    private function jsonGet(string $path, array $query = []): array
    {
        try {
            $response = $this->http()->get($this->url($path), $query);
        } catch (\Throwable $e) {
            Log::warning('checkface.get_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 502, 'data' => [], 'message' => 'Face service unavailable.'];
        }

        return $this->normalizeResponse($response->status(), $response->json());
    }

    /**
     * @param  list<array<string, mixed>>  $multipart
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    private function multipartPost(string $path, array $multipart, ?int $timeoutSeconds = null): array
    {
        try {
            $response = $this->http($timeoutSeconds)->asMultipart()->post($this->url($path), $multipart);
        } catch (\Throwable $e) {
            Log::warning('checkface.post_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 502, 'data' => [], 'message' => 'Face service unavailable.'];
        }

        return $this->normalizeResponse($response->status(), $response->json());
    }

    private function http(?int $timeoutSeconds = null): PendingRequest
    {
        $timeout = $timeoutSeconds ?? (int) config('checkface.timeout_seconds', 45);

        return Http::withToken((string) config('checkface.api_token'))
            ->timeout($timeout)
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('checkface.base_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  UploadedFile|array{contents: string, filename: string, mime?: string}  $file
     * @return array<string, mixed>
     */
    private function filePart(string $name, UploadedFile|array $file): array
    {
        if ($file instanceof UploadedFile) {
            return [
                'name' => $name,
                'contents' => fopen($file->getRealPath(), 'r'),
                'filename' => $file->getClientOriginalName() ?: 'photo.jpg',
                'headers' => ['Content-Type' => $file->getMimeType() ?: 'image/jpeg'],
            ];
        }

        return [
            'name' => $name,
            'contents' => $file['contents'],
            'filename' => $file['filename'] ?? 'upload.bin',
            'headers' => ['Content-Type' => $file['mime'] ?? 'application/octet-stream'],
        ];
    }

    /**
     * @param  mixed  $json
     * @return array{ok: bool, status: int, data: array<string, mixed>, message: string}
     */
    private function normalizeResponse(int $status, mixed $json): array
    {
        $data = is_array($json) ? $json : [];
        $detail = '';
        if (isset($data['detail'])) {
            $detail = is_string($data['detail']) ? $data['detail'] : 'Face check failed.';
        } elseif (isset($data['message']) && is_string($data['message'])) {
            $detail = $data['message'];
        }

        $ok = $status >= 200 && $status < 300;

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'message' => $detail !== '' ? $detail : ($ok ? 'OK' : 'Face check failed.'),
        ];
    }
}
