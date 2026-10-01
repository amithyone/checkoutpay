<?php

namespace App\Services\Consumer;

use Illuminate\Support\Facades\Log;

/**
 * Soft/hard scoring of client IMU timelines (motion_json) for CheckFace liveness.
 * Does not replace CheckFace video match — extra anti-spoof signal only.
 */
class FaceMotionJsonScorer
{
    /**
     * @return array{
     *   ok: bool,
     *   soft: bool,
     *   score: float|null,
     *   error_code: string|null,
     *   message: string|null,
     *   reasons: list<string>
     * }
     */
    public function evaluate(?string $rawJson, ?string $expectedSessionId = null): array
    {
        $hard = (bool) config('checkface.motion_json_hard_fail', false);

        if ($rawJson === null || trim($rawJson) === '') {
            return $this->pass(null, ['motion_json_omitted'], soft: true);
        }

        try {
            $data = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            Log::info('face_motion_json_parse_failed', ['hard' => $hard]);

            return $hard
                ? $this->fail('motion_mismatch', 'Invalid motion_json.', ['invalid_json'])
                : $this->pass(0.0, ['invalid_json'], soft: true);
        }

        if (! is_array($data)) {
            return $hard
                ? $this->fail('motion_mismatch', 'Invalid motion_json.', ['not_object'])
                : $this->pass(0.0, ['not_object'], soft: true);
        }

        $version = (int) ($data['protocol_version'] ?? 0);
        if ($version !== 1) {
            Log::info('face_motion_json_version', ['version' => $version, 'hard' => $hard]);

            return $hard
                ? $this->fail('motion_mismatch', 'Unsupported motion_json protocol_version.', ['protocol_version'])
                : $this->pass(0.0, ['protocol_version'], soft: true);
        }

        if ($expectedSessionId !== null && $expectedSessionId !== '') {
            $motionSession = (string) ($data['session_id'] ?? '');
            if ($motionSession !== '' && ! hash_equals($expectedSessionId, $motionSession)) {
                Log::warning('face_motion_json_session_mismatch', [
                    'expected' => $expectedSessionId,
                    'got' => $motionSession,
                    'hard' => $hard,
                ]);

                return $hard
                    ? $this->fail('motion_mismatch', 'motion_json session_id does not match liveness session.', ['session_id_mismatch'])
                    : $this->pass(0.0, ['session_id_mismatch'], soft: true);
            }
        }

        if (! (bool) ($data['available'] ?? false)) {
            // Sensors missing/denied — accept media; policy soft by default.
            return $this->pass(null, ['sensors_unavailable'], soft: true);
        }

        $samples = $data['samples'] ?? [];
        if (! is_array($samples)) {
            $samples = [];
        }

        $started = (int) ($data['started_at_ms'] ?? 0);
        $ended = (int) ($data['ended_at_ms'] ?? 0);
        $durationMs = max(0, $ended - $started);
        if ($durationMs <= 0 && $samples !== []) {
            $times = array_map(fn ($s) => (int) ($s['t_ms'] ?? 0), $samples);
            $durationMs = max(0, (max($times) ?: 0) - (min($times) ?: 0));
        }

        $hz = max(1.0, (float) ($data['sample_hz'] ?? 20));
        $durationS = max(0.1, $durationMs / 1000.0);
        $minSamples = (int) floor(0.5 * $durationS * $hz);

        $reasons = [];
        if (count($samples) < max(3, $minSamples)) {
            $reasons[] = 'sparse_samples';
        }

        $gyroMags = [];
        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $gx = (float) ($sample['gx'] ?? 0);
            $gy = (float) ($sample['gy'] ?? 0);
            $gz = (float) ($sample['gz'] ?? 0);
            $gyroMags[] = sqrt(($gx * $gx) + ($gy * $gy) + ($gz * $gz));
        }

        $peak = $gyroMags === [] ? 0.0 : max($gyroMags);
        $mean = $gyroMags === [] ? 0.0 : (array_sum($gyroMags) / count($gyroMags));
        $variance = 0.0;
        if (count($gyroMags) > 1) {
            $acc = 0.0;
            foreach ($gyroMags as $m) {
                $acc += ($m - $mean) ** 2;
            }
            $variance = $acc / count($gyroMags);
        }

        // Near-flat IMU across the whole clip looks like video replay without phone motion.
        $flatPeak = (float) config('checkface.motion_json_flat_peak', 0.02);
        $flatVar = (float) config('checkface.motion_json_flat_variance', 0.00005);
        if ($peak < $flatPeak && $variance < $flatVar) {
            $reasons[] = 'flat_imu';
        }

        $challenges = is_array($data['challenges'] ?? null) ? $data['challenges'] : [];
        foreach ($challenges as $challenge) {
            if (! is_array($challenge)) {
                continue;
            }
            $id = strtolower((string) ($challenge['id'] ?? ''));
            if (! in_array($id, ['left', 'right', 'turn_left', 'turn_right'], true)) {
                continue;
            }
            $startMs = (int) ($challenge['start_ms'] ?? -1);
            $endMs = (int) ($challenge['end_ms'] ?? -1);
            if ($startMs < 0 || $endMs <= $startMs) {
                continue;
            }
            $window = [];
            foreach ($samples as $i => $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $t = (int) ($sample['t_ms'] ?? 0);
                if ($t >= $startMs && $t <= $endMs) {
                    $window[] = $gyroMags[$i] ?? 0.0;
                }
            }
            $wPeak = $window === [] ? 0.0 : max($window);
            if ($wPeak < $flatPeak * 2) {
                $reasons[] = 'weak_turn_'.$id;
            }
        }

        $score = 1.0;
        if (in_array('flat_imu', $reasons, true)) {
            $score -= 0.6;
        }
        if (in_array('sparse_samples', $reasons, true)) {
            $score -= 0.25;
        }
        foreach ($reasons as $r) {
            if (str_starts_with($r, 'weak_turn_')) {
                $score -= 0.1;
            }
        }
        $score = max(0.0, min(1.0, $score));

        Log::info('face_motion_json_scored', [
            'score' => $score,
            'reasons' => $reasons,
            'sample_count' => count($samples),
            'peak' => round($peak, 5),
            'hard' => $hard,
            // Do not log raw samples (biometric-adjacent).
        ]);

        $failReasons = array_values(array_filter(
            $reasons,
            fn (string $r) => $r === 'flat_imu' || str_starts_with($r, 'weak_turn_') || $r === 'sparse_samples'
        ));

        if ($hard && (
            in_array('flat_imu', $failReasons, true)
            || ($failReasons !== [] && $score < (float) config('checkface.motion_json_min_score', 0.35))
        )) {
            return $this->fail('motion_mismatch', 'Phone motion did not match guided prompts.', $failReasons, $score);
        }

        return $this->pass($score, $reasons, soft: true);
    }

    /**
     * @param  list<string>  $reasons
     * @return array{ok: bool, soft: bool, score: float|null, error_code: string|null, message: string|null, reasons: list<string>}
     */
    private function pass(?float $score, array $reasons, bool $soft): array
    {
        return [
            'ok' => true,
            'soft' => $soft,
            'score' => $score,
            'error_code' => null,
            'message' => null,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  list<string>  $reasons
     * @return array{ok: bool, soft: bool, score: float|null, error_code: string|null, message: string|null, reasons: list<string>}
     */
    private function fail(string $code, string $message, array $reasons, ?float $score = null): array
    {
        return [
            'ok' => false,
            'soft' => false,
            'score' => $score,
            'error_code' => $code,
            'message' => $message,
            'reasons' => $reasons,
        ];
    }
}
