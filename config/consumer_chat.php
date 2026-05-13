<?php

return [
    /**
     * Shared secret for POST /api/v1/internal/consumer-chat/reply (support / automation).
     * Send header: X-Consumer-Chat-Key: <value>
     */
    'internal_reply_key' => (string) env('CONSUMER_CHAT_INTERNAL_REPLY_KEY', ''),

    'max_body_chars' => max(100, min(8000, (int) env('CONSUMER_CHAT_MAX_BODY', 4000))),
];
