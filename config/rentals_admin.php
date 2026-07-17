<?php

return [
    /** Sanctum personal access token name for the rentals admin mobile/web app. */
    'token_name' => env('RENTALS_ADMIN_TOKEN_NAME', 'rentals-admin'),

    /**
     * Idle timeout for rentals admin app sessions (minutes).
     * After this many minutes without a touched request, the session ends and the token is revoked.
     */
    'app_session_idle_minutes' => max(1, (int) env('RENTALS_ADMIN_APP_SESSION_IDLE_MINUTES', 480)),
];
