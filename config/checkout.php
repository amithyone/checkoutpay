<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global match: max emails per run
    |--------------------------------------------------------------------------
    |
    | Only the newest unmatched processed emails (by created_at) are considered
    | each time global match runs (cron or admin). This keeps runs fast and avoids
    | cron timeouts. Older backlog is picked up on subsequent runs.
    | Set GLOBAL_MATCH_MAX_EMAILS in .env to tune (minimum 1).
    |
    */
    'global_match_max_emails' => max(1, (int) env('GLOBAL_MATCH_MAX_EMAILS', 200)),

];
