<?php

return [
    'default_country' => strtoupper((string) env('SUPPORT_DEFAULT_COUNTRY', 'NG')),

    'whatsapp_welcome' => env(
        'SUPPORT_WHATSAPP_WELCOME',
        "*:brand* support\n\nWe saved your support chat. Reply here on WhatsApp or continue on our website.\n\nRefunds, when approved, are credited to your WhatsApp wallet — you can transfer to any Nigerian bank from the app or wallet menu."
    ),

    'poll_interval_seconds' => (int) env('SUPPORT_POLL_INTERVAL_SECONDS', 5),

    /** @deprecated Use rate_limit_poll_per_minute / rate_limit_write_per_minute */
    'rate_limit_per_minute' => (int) env('SUPPORT_RATE_LIMIT_PER_MINUTE', 20),

    'rate_limit_poll_per_minute' => (int) env('SUPPORT_RATE_LIMIT_POLL_PER_MINUTE', 120),
    'rate_limit_write_per_minute' => (int) env('SUPPORT_RATE_LIMIT_WRITE_PER_MINUTE', 40),
    'rate_limit_start_per_minute' => (int) env('SUPPORT_RATE_LIMIT_START_PER_MINUTE', 15),

    /** Send WhatsApp welcome when linking wallet on web widget (not CheckoutNow in-app). */
    'send_whatsapp_welcome_on_web' => (bool) env('SUPPORT_SEND_WHATSAPP_WELCOME_ON_WEB', true),

    'copy' => [
        'anonymous_consent' => 'I agree to chat with CheckoutPay support. This session stays in the browser on this device unless I link WhatsApp.',
        'wallet_consent' => 'I agree to link my WhatsApp number, create or use a CheckoutPay WhatsApp wallet, receive a WhatsApp message, and understand refunds may be credited to that wallet.',
        'checkoutnow_logged_in_intro' => 'You are logged in. Messages stay in this app and our team sees your linked wallet.',
    ],

    /*
    | Quick support issue types (widget / CheckoutNow). Keys stored on support_tickets.issue_type.
    | requires_payment: visitor must submit session ID (transaction_id) + amount paid.
    */
    'issue_types' => [
        'payment_pending_transfer' => [
            'label' => 'I transferred but payment is still pending',
            'hint' => 'Enter the session ID from your bank transfer (transfer details or receipt) and the exact amount you sent.',
            'subject_prefix' => 'Payment pending',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
        ],
        'payment_not_confirmed' => [
            'label' => 'Payment not confirmed / no success page',
            'hint' => 'Bank session ID from your transfer receipt and amount help us match your payment quickly.',
            'subject_prefix' => 'Payment not confirmed',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
        ],
        'payment_wrong_amount' => [
            'label' => 'Wrong amount or mismatch',
            'hint' => 'Include the bank session ID from your receipt and what you paid vs what was requested.',
            'subject_prefix' => 'Payment amount issue',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
        ],
        'payment_expired' => [
            'label' => 'Session expired but I already paid',
            'hint' => 'Share the bank session ID from your transfer and the amount you sent.',
            'subject_prefix' => 'Expired session',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
        ],
        'general' => [
            'label' => 'Other question',
            'hint' => 'General billing or product help.',
            'subject_prefix' => 'Support',
            'requires_payment' => false,
            'quick' => false,
            'priority' => 'medium',
        ],
    ],
];
