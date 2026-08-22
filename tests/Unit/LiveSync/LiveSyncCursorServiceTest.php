<?php

namespace Tests\Unit\LiveSync;

use App\Models\LiveSyncOutboundCursor;
use App\Services\LiveSync\LiveSyncCursorService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveSyncCursorServiceTest extends TestCase
{
    private const TEST_ENTITY = 'test_cursor_payment';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('live_sync_outbound_cursors')) {
            $this->markTestSkipped('live_sync_outbound_cursors table not migrated');
        }
        LiveSyncOutboundCursor::query()->where('entity', self::TEST_ENTITY)->delete();
    }

    protected function tearDown(): void
    {
        LiveSyncOutboundCursor::query()->where('entity', self::TEST_ENTITY)->delete();
        parent::tearDown();
    }

    public function test_advance_and_mark_caught_up(): void
    {
        $service = app(LiveSyncCursorService::class);

        $service->advance(self::TEST_ENTITY, 100, 5);
        $cursor = $service->cursorFor(self::TEST_ENTITY);

        $this->assertSame(100, (int) $cursor->last_origin_id);
        $this->assertSame(5, (int) $cursor->rows_pushed_total);
        $this->assertSame(LiveSyncOutboundCursor::STATUS_BACKFILL, $cursor->status);

        $service->markCaughtUp(self::TEST_ENTITY, 500);
        $cursor->refresh();

        $this->assertSame(LiveSyncOutboundCursor::STATUS_CAUGHT_UP, $cursor->status);
        $this->assertSame(500, (int) $cursor->max_origin_id);
    }

    public function test_reset_clears_cursor(): void
    {
        $service = app(LiveSyncCursorService::class);
        $service->advance(self::TEST_ENTITY, 999, 10);
        $service->reset(self::TEST_ENTITY);

        $cursor = $service->cursorFor(self::TEST_ENTITY);
        $this->assertSame(0, (int) $cursor->last_origin_id);
        $this->assertSame(LiveSyncOutboundCursor::STATUS_BACKFILL, $cursor->status);
        $this->assertSame(0, (int) $cursor->rows_pushed_total);
    }

    public function test_advance_if_higher_only_moves_forward(): void
    {
        $service = app(LiveSyncCursorService::class);
        $service->advance(self::TEST_ENTITY, 200, 0);
        $service->advanceIfHigher(self::TEST_ENTITY, 150);
        $this->assertSame(200, $service->lastOriginId(self::TEST_ENTITY));
        $service->advanceIfHigher(self::TEST_ENTITY, 250);
        $this->assertSame(250, $service->lastOriginId(self::TEST_ENTITY));
    }
}
