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

    'payee_banks' => [
        'rubies_mfb' => [
            'label' => 'Rubies MFB',
            'requires_session_id' => true,
            'source' => 'external_api',
        ],
        'moniepoint_mfb' => [
            'label' => 'Moniepoint MFB',
            'requires_session_id' => true,
            'source' => 'internal',
        ],
        'kuda' => [
            'label' => 'Kuda Bank',
            'requires_session_id' => false,
            'source' => 'internal',
        ],
    ],

    /** How many days back to search payments/inbox when a visitor reports a transfer (Kuda/Moniepoint). */
    'intake_lookup_days' => (int) env('SUPPORT_INTAKE_LOOKUP_DAYS', 30),

    /** How far back to look for already-approved / matched-inbox credits (can be longer than unmatched scan). */
    'intake_credited_lookup_days' => (int) env('SUPPORT_INTAKE_CREDITED_LOOKUP_DAYS', 180),

    'intake_messages' => [
        'disclaimer' => "Hi — we're CheckoutPay (Checkout Now Ltd), a payment gateway. We process bank transfers only. We are not the shop or website you paid — we don't deliver products or handle merchant service issues.",
        'ask_payment_issue' => 'Is this about a bank transfer you made to pay for something online (instant payment)?',
        'rejected_non_payment' => 'We only help with instant bank transfer problems (money sent to our checkout account). For product, delivery, or service issues, please contact the website or seller where you started checkout.',
        'ask_destination_account' => 'What is the account number you sent money TO? (From your bank receipt.)',
        'account_confirmed_ours' => 'Yes — that is one of our CheckoutPay collection accounts.',
        'pending_payment_found' => 'We found :count pending checkout payment(s) waiting on that account. We will try to match your transfer.',
        'pending_payment_none' => 'We do not see a pending checkout on that account right now, but we will still check recent bank notifications.',
        'inbox_unmatched_found' => 'We found :count recent unmatched bank notification(s) for that account in our inbox. Share your name and amount so we can match it.',
        'ask_session_id' => 'What is the bank session ID on your transfer receipt or SMS? (Not the website URL.)',
        'ask_session_id_before_chat' => 'Before we connect you with customer care, please enter the bank session ID from your transfer receipt or SMS.',
        'session_id_required_for_chat' => 'Please enter your bank session ID first so we can look up your Rubies transfer, then you can continue in this chat.',
        'match_not_found_internal' => "We couldn't match your transfer yet with the account, name, and amount provided.",
        'ask_moniepoint_charges' => 'For Moniepoint transfers: did you send the full checkout amount including all fees/charges shown on the payment page?',
        'moniepoint_fix_amount' => 'Please go back to the merchant checkout page and update the amount you intend to pay so it includes Moniepoint charges, then pay again. Our checkout API lets merchants correct the intended amount before you transfer. Tap *Start over* when you are ready to try again.',
        'match_searching' => 'Thanks — searching for your pending payment and trying to match it now…',
        'match_success' => 'Good news: your payment has been matched and approved. You can return to the merchant site — it should update shortly. Our team is also notified if you need anything else.',
        'match_not_received' => "We haven't received your transfer yet, or we couldn't match it with the details provided. Please try again in a few minutes.",
        'match_not_found' => "We couldn't find a pending payment with those details. Please double-check your account number and bank session ID.",
        'ask_match_amount_verify' => 'Did you transfer the exact amount shown on the merchant checkout page, including any bank fees or charges?',
        'checkout_fix_amount' => 'Please go back to the website or app where you started checkout and update the amount you intend to pay so it matches what you actually transferred (including any bank charges). Then pay again if needed. Tap *Start over* when you are ready to check for your transaction again.',
        'match_not_pending' => 'This payment is no longer pending in our system. Continue below if you still need help.',
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
        'whatsapp_requires_verification' => 'Link WhatsApp after we verify your payment details (session ID for Rubies transfers, or account + name + amount for Kuda/Moniepoint).',
        'ask_phone' => 'Your WhatsApp number (with country):',
        'ready_to_complete' => 'Thanks — starting your chat with our team.',
    ],

    'copy' => [
        'anonymous_consent' => 'I agree to chat with CheckoutPay support. This session stays in the browser on this device unless I link WhatsApp.',
        'wallet_consent' => 'I agree to link my WhatsApp number, create or use a CheckoutPay WhatsApp wallet, receive a WhatsApp message after my payment details are verified, and understand refunds may be credited to that wallet.',
        'checkoutnow_logged_in_intro' => 'You are logged in. Messages stay in this app and our team sees your linked wallet.',
    ],

    'support_categories' => [
        ['key' => 'payment', 'label' => 'Payment / checkout transfer'],
        ['key' => 'wallet', 'label' => 'Wallet support'],
    ],

    /*
    | Quick support issue types (widget / CheckoutNow). Keys stored on support_tickets.issue_type.
    | requires_payment: visitor must submit session ID (transaction_id) + amount paid.
    | queue: payment (default) or wallet — routes ticket to the wallet support desk.
    */
    'issue_types' => [
        'payment_pending_transfer' => [
            'label' => 'I transferred but payment is still pending',
            'hint' => 'Enter the session ID from your bank transfer (transfer details or receipt) and the exact amount you sent.',
            'subject_prefix' => 'Payment pending',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'payment',
        ],
        'payment_not_confirmed' => [
            'label' => 'Payment not confirmed / no success page',
            'hint' => 'Bank session ID from your transfer receipt and amount help us match your payment quickly.',
            'subject_prefix' => 'Payment not confirmed',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'payment',
        ],
        'payment_wrong_amount' => [
            'label' => 'Wrong amount or mismatch',
            'hint' => 'Include the bank session ID from your receipt and what you paid vs what was requested.',
            'subject_prefix' => 'Payment amount issue',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'payment',
        ],
        'payment_expired' => [
            'label' => 'Session expired but I already paid',
            'hint' => 'Share the bank session ID from your transfer and the amount you sent.',
            'subject_prefix' => 'Expired session',
            'requires_payment' => true,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'payment',
        ],
        'general' => [
            'label' => 'Other question',
            'hint' => 'General billing or product help.',
            'subject_prefix' => 'Support',
            'requires_payment' => false,
            'quick' => false,
            'priority' => 'medium',
            'queue' => 'payment',
        ],
        'account_deletion' => [
            'label' => 'Delete my account and data',
            'hint' => 'Request closure of your CheckoutNow or WhatsApp Wallet account. Zero your balance first. You can also use check-outpay.com/account-deletion.',
            'subject_prefix' => 'Account deletion',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'medium',
            'queue' => 'wallet',
        ],
        'wallet_transfer' => [
            'label' => 'Transfer / payout problem',
            'hint' => 'Describe the transfer, recipient, and any error message you saw.',
            'subject_prefix' => 'Wallet transfer',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'wallet',
        ],
        'wallet_balance' => [
            'label' => 'Balance / top-up issue',
            'hint' => 'Tell us what you tried to add and what happened to your balance.',
            'subject_prefix' => 'Wallet balance',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'high',
            'queue' => 'wallet',
        ],
        'wallet_card' => [
            'label' => 'Virtual card issue',
            'hint' => 'Describe the card problem (create, fund, withdraw, or decline).',
            'subject_prefix' => 'Virtual card',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'medium',
            'queue' => 'wallet',
        ],
        'wallet_account' => [
            'label' => 'PIN, login, or account help',
            'hint' => 'Describe the account or security issue (do not share your PIN).',
            'subject_prefix' => 'Wallet account',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'medium',
            'queue' => 'wallet',
        ],
        'wallet_other' => [
            'label' => 'Other wallet question',
            'hint' => 'General CheckoutNow wallet or app help.',
            'subject_prefix' => 'Wallet support',
            'requires_payment' => false,
            'quick' => true,
            'priority' => 'medium',
            'queue' => 'wallet',
        ],
    ],
];
