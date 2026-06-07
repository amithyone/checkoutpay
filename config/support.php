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

    /** Wrong payee account tries before temporary lockout (per IP). */
    'intake_wrong_account_max_attempts' => (int) env('SUPPORT_INTAKE_WRONG_ACCOUNT_MAX_ATTEMPTS', 5),

    /** Minutes to wait after max wrong accounts before starting intake again. */
    'intake_lockout_minutes' => (int) env('SUPPORT_INTAKE_LOCKOUT_MINUTES', 10),

    'payee_name_patterns' => [
        'checkout now',
        'checkout now ltd',
        'checkoutpay',
    ],

    'intake_messages' => [
        'disclaimer' => "Hi — we're CheckoutPay (Checkout Now Ltd), a payment gateway. We process bank transfers only. We are not the shop or website you paid — we don't deliver products or handle merchant service issues.",
        'ask_payment_issue' => 'Is this about a bank transfer you made to pay for something online (instant payment)?',
        'rejected_non_payment' => 'We only help with instant bank transfer problems (money sent to our checkout account). For product, delivery, or service issues, please contact the website or seller where you started checkout.',
        'ask_destination_account' => 'What is the account number you sent money TO? (From your bank receipt.)',
        'ask_session_id' => 'What is the bank session ID on your transfer receipt or SMS? (Not the website URL.)',
        'account_mismatch' => "That account number doesn't match the account on this payment session. Check your receipt and try again.",
        'not_our_account' => 'This account number is not one we operate. If you paid a different website, contact them directly — we only handle transfers to CheckoutPay / Checkout Now accounts.',
        'not_our_account_retry' => 'If you have another receipt, enter the account number you sent money TO on that transfer. You can also tap Restart to begin again.',
        'locked_out' => 'Too many account numbers that are not ours were entered. Please wait :minutes minutes, then you can start support again.',
        'session_not_found' => "We couldn't find this session ID yet. You can continue in this chat and our team will try to match your transfer.",
        'payment_pending' => 'Your payment is still pending. We will ask our banking partner to trace it. Keep your bank session ID handy.',
        'payment_approved' => 'This payment shows as approved in our system. If the merchant site did not update, tell us below and our team will help.',
        'payment_expired' => 'This payment session has expired. If you already transferred, our team can still review with your bank session ID and receipt.',
        'ask_name' => 'What is your name?',
        'ask_amount' => 'How much did you transfer (₦)?',
        'ask_bank_from' => 'Which bank did you send from?',
        'ask_receipt' => 'You can upload a photo of your transfer receipt (optional). Send a file or type "skip".',
        'ask_contact_mode' => 'How should we follow up?',
        'whatsapp_requires_verification' => 'Link WhatsApp after we confirm your session ID matches the account you paid to.',
        'ask_phone' => 'Your WhatsApp number (with country):',
        'ready_to_complete' => 'Thanks — starting your chat with our team.',
    ],

    'copy' => [
        'anonymous_consent' => 'I agree to chat with CheckoutPay support. This session stays in the browser on this device unless I link WhatsApp.',
        'wallet_consent' => 'I agree to link my WhatsApp number, create or use a CheckoutPay WhatsApp wallet, receive a WhatsApp message after my payment details are verified, and understand refunds may be credited to that wallet.',
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
        'account_deletion' => [
            'label' => 'Delete my account and data',
            'hint' => 'Request closure of your CheckoutNow or WhatsApp Wallet account. Zero your balance first. You can also use check-outpay.com/account-deletion.',
            'subject_prefix' => 'Account deletion',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'medium',
        ],
    ],
];
