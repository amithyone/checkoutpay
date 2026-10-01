<?php

return [
    /** Master switch for wallet face-check gating and consumer face APIs. */
    'enabled' => filter_var(env('CHECKFACE_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** CheckFace FastAPI base URL (no trailing slash). Prefer loopback when co-located. */
    'base_url' => rtrim((string) env('CHECKFACE_BASE_URL', 'http://127.0.0.1:8025'), '/'),

    /** Bearer token shared with CheckFace (tenant cf_live_… key or server master token). */
    'api_token' => (string) env('CHECKFACE_API_TOKEN', ''),

    /** HTTP timeout seconds for enroll/verify calls. Video uses video_timeout_seconds. */
    'timeout_seconds' => max(5, (int) env('CHECKFACE_TIMEOUT_SECONDS', 45)),

    /** Liveness video can exceed 45s on cold or remote hosts (Namecheap → Contabo). */
    'video_timeout_seconds' => max(45, (int) env('CHECKFACE_VIDEO_TIMEOUT_SECONDS', 90)),

    /** Amount (NGN) at/above which unfamiliar recipients require a fresh face check. */
    'amount_threshold_ngn' => (float) env('CHECKFACE_AMOUNT_THRESHOLD_NGN', 30000),

    /** Successful sends to the same destination before it counts as "frequent". */
    'frequent_min_transfers' => max(1, (int) env('CHECKFACE_FREQUENT_MIN_TRANSFERS', 3)),

    /** Look back this many days when counting frequent recipients (0 = all time). */
    'frequent_lookback_days' => max(0, (int) env('CHECKFACE_FREQUENT_LOOKBACK_DAYS', 365)),

    /** Short-lived face challenge token TTL after a successful selfie/liveness check. */
    'challenge_ttl_minutes' => max(1, (int) env('CHECKFACE_CHALLENGE_TTL_MINUTES', 5)),

    /**
     * When CHECKFACE_API_TOKEN is empty, allow auto-create of a first-party CheckFace account
     * (@check-outnow.com / @check-outpay.com) via POST /v1/partner/register.
     */
    'auto_provision' => filter_var(env('CHECKFACE_AUTO_PROVISION', true), FILTER_VALIDATE_BOOL),
    'provision_email' => (string) env('CHECKFACE_PROVISION_EMAIL', 'wallet@check-outnow.com'),
    'provision_password' => (string) env('CHECKFACE_PROVISION_PASSWORD', ''),
    'provision_name' => (string) env('CHECKFACE_PROVISION_NAME', 'CheckoutNow Wallet'),
    'provision_company' => (string) env('CHECKFACE_PROVISION_COMPANY', 'CheckoutNow'),
    'provision_key_label' => (string) env('CHECKFACE_PROVISION_KEY_LABEL', 'CheckoutNow Wallet'),

    /** Secrets file where CHECKFACE_API_TOKEN is written (this host uses `.error`). */
    'secrets_file' => (string) env('CHECKFACE_SECRETS_FILE', base_path('.error')),

    /**
     * Client IMU timeline (motion_json) anti-spoof — soft by default (log only).
     * Set CHECKFACE_MOTION_JSON_HARD_FAIL=true to reject with error_code motion_mismatch.
     */
    'motion_json_hard_fail' => filter_var(env('CHECKFACE_MOTION_JSON_HARD_FAIL', false), FILTER_VALIDATE_BOOL),
    'motion_json_min_score' => (float) env('CHECKFACE_MOTION_JSON_MIN_SCORE', 0.35),
    'motion_json_flat_peak' => (float) env('CHECKFACE_MOTION_JSON_FLAT_PEAK', 0.02),
    'motion_json_flat_variance' => (float) env('CHECKFACE_MOTION_JSON_FLAT_VARIANCE', 0.00005),

    /**
     * Max CheckFace API hits per minute per user/device (session + video share this bucket).
     * Floor enforced in RateLimiter is 3; default 12 allows retries without OTP friction.
     */
    'rate_limit_per_minute' => max(3, (int) env('CHECKFACE_RATE_LIMIT_PER_MINUTE', 12)),

    /**
     * Public API host the native app should use for step-up face (Contabo).
     * Login/OTP stay on check-outpay.com; face session+video go here.
     */
    'app_face_api_base' => rtrim((string) env('CHECKFACE_APP_API_BASE', 'https://check-outnow.com'), '/'),

    /**
     * HMAC bridge between Namecheap (session DB) and Contabo (CheckFace).
     * Same CHECKFACE_STEPUP_BRIDGE_SECRET on both hosts.
     */
    'stepup_bridge_enabled' => filter_var(env('CHECKFACE_STEPUP_BRIDGE_ENABLED', true), FILTER_VALIDATE_BOOL),
    'stepup_bridge_secret' => (string) env('CHECKFACE_STEPUP_BRIDGE_SECRET', ''),
    'stepup_bridge_finalize_url' => rtrim((string) env(
        'CHECKFACE_STEPUP_BRIDGE_FINALIZE_URL',
        'https://check-outpay.com/api/v1/internal/stepup/face-complete'
    ), '/'),
    'stepup_bridge_continue_ttl_seconds' => max(60, (int) env('CHECKFACE_STEPUP_BRIDGE_CONTINUE_TTL', 1800)),
    'stepup_bridge_proof_ttl_seconds' => max(30, (int) env('CHECKFACE_STEPUP_BRIDGE_PROOF_TTL', 300)),
];
