<?php

namespace Tests\Unit\Services\Consumer;

use App\Services\Consumer\FaceMotionJsonScorer;
use Tests\TestCase;

class FaceMotionJsonScorerTest extends TestCase
{
    public function test_omitted_motion_json_passes_soft(): void
    {
        $r = (new FaceMotionJsonScorer)->evaluate(null, 'liv_1');
        $this->assertTrue($r['ok']);
        $this->assertContains('motion_json_omitted', $r['reasons']);
    }

    public function test_unavailable_sensors_pass(): void
    {
        $json = json_encode([
            'protocol_version' => 1,
            'available' => false,
            'session_id' => 'liv_1',
            'samples' => [],
        ]);
        $r = (new FaceMotionJsonScorer)->evaluate($json, 'liv_1');
        $this->assertTrue($r['ok']);
        $this->assertContains('sensors_unavailable', $r['reasons']);
    }

    public function test_session_mismatch_soft_by_default(): void
    {
        config(['checkface.motion_json_hard_fail' => false]);
        $json = json_encode([
            'protocol_version' => 1,
            'available' => true,
            'session_id' => 'liv_other',
            'sample_hz' => 20,
            'started_at_ms' => 0,
            'ended_at_ms' => 1000,
            'samples' => [
                ['t_ms' => 0, 'gx' => 0.5, 'gy' => 0.1, 'gz' => 0.0, 'ax' => 0, 'ay' => 9.8, 'az' => 0],
                ['t_ms' => 500, 'gx' => 0.4, 'gy' => 0.2, 'gz' => 0.1, 'ax' => 0, 'ay' => 9.8, 'az' => 0],
                ['t_ms' => 1000, 'gx' => 0.3, 'gy' => 0.0, 'gz' => 0.0, 'ax' => 0, 'ay' => 9.8, 'az' => 0],
            ],
        ]);
        $r = (new FaceMotionJsonScorer)->evaluate($json, 'liv_1');
        $this->assertTrue($r['ok']);
        $this->assertContains('session_id_mismatch', $r['reasons']);
    }

    public function test_session_mismatch_hard_fail(): void
    {
        config(['checkface.motion_json_hard_fail' => true]);
        $json = json_encode([
            'protocol_version' => 1,
            'available' => true,
            'session_id' => 'liv_other',
            'samples' => [],
        ]);
        $r = (new FaceMotionJsonScorer)->evaluate($json, 'liv_1');
        $this->assertFalse($r['ok']);
        $this->assertSame('motion_mismatch', $r['error_code']);
    }

    public function test_flat_imu_soft_passes_with_low_score(): void
    {
        config(['checkface.motion_json_hard_fail' => false]);
        $samples = [];
        for ($t = 0; $t <= 5000; $t += 50) {
            $samples[] = ['t_ms' => $t, 'gx' => 0.0, 'gy' => 0.0, 'gz' => 0.0, 'ax' => 0.0, 'ay' => 9.8, 'az' => 0.0];
        }
        $json = json_encode([
            'protocol_version' => 1,
            'available' => true,
            'session_id' => 'liv_1',
            'sample_hz' => 20,
            'started_at_ms' => 0,
            'ended_at_ms' => 5000,
            'challenges' => [
                ['id' => 'left', 'start_ms' => 1000, 'end_ms' => 3000],
            ],
            'samples' => $samples,
        ]);
        $r = (new FaceMotionJsonScorer)->evaluate($json, 'liv_1');
        $this->assertTrue($r['ok']);
        $this->assertContains('flat_imu', $r['reasons']);
        $this->assertLessThan(0.5, (float) $r['score']);
    }

    public function test_flat_imu_hard_fail(): void
    {
        config([
            'checkface.motion_json_hard_fail' => true,
            'checkface.motion_json_min_score' => 0.35,
        ]);
        $samples = [];
        for ($t = 0; $t <= 5000; $t += 50) {
            $samples[] = ['t_ms' => $t, 'gx' => 0.0, 'gy' => 0.0, 'gz' => 0.0, 'ax' => 0.0, 'ay' => 9.8, 'az' => 0.0];
        }
        $json = json_encode([
            'protocol_version' => 1,
            'available' => true,
            'session_id' => 'liv_1',
            'sample_hz' => 20,
            'started_at_ms' => 0,
            'ended_at_ms' => 5000,
            'samples' => $samples,
        ]);
        $r = (new FaceMotionJsonScorer)->evaluate($json, 'liv_1');
        $this->assertFalse($r['ok']);
        $this->assertSame('motion_mismatch', $r['error_code']);
    }
}
