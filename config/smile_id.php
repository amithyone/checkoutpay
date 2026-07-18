<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smile ID (Kenya Tier 2 / IPRS Basic KYC)
    |--------------------------------------------------------------------------
    |
    | Create a partner account at https://portal.usesmileid.com/
    | Enable Kenya National ID Basic KYC (sandbox first).
    | Use sandbox API key + partner ID until production KYC with Smile is complete.
    |
    */
    'partner_id' => (string) env('SMILE_ID_PARTNER_ID', ''),
    'api_key' => (string) env('SMILE_ID_API_KEY', ''),
    'sandbox' => filter_var(env('SMILE_ID_SANDBOX', true), FILTER_VALIDATE_BOOL),
    'timeout_seconds' => max(5, (int) env('SMILE_ID_TIMEOUT_SECONDS', 45)),
    /** Accept Exact Match (1020) and Partial Match (1021). */
    'accept_partial_match' => filter_var(env('SMILE_ID_ACCEPT_PARTIAL_MATCH', true), FILTER_VALIDATE_BOOL),
];
