<?php

namespace App\Jobs;

use App\Services\LiveSync\LiveSyncCursorService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LiveSyncPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(LiveSyncTransmitterClient $client, LiveSyncCursorService $cursors): void
    {
        $result = $client->send($this->payload);
        if (! ($result['ok'] ?? false)) {
            Log::warning('live_sync.job_failed', [
                'event_id' => $result['event_id'] ?? null,
                'entity' => $this->payload['entity'] ?? null,
                'message' => $result['message'] ?? null,
                'http_status' => $result['status'] ?? null,
            ]);

            throw new \RuntimeException('Live sync push failed: '.((string) ($result['message'] ?? 'unknown')));
        }

        $entity = (string) ($this->payload['entity'] ?? '');
        $originId = (int) (($this->payload['data'] ?? [])['_origin_id'] ?? 0);
        if ($entity !== '' && $originId > 0) {
            $cursors->advanceIfHigher($entity, $originId);
        }
    }
}
