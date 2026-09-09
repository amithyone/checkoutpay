<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Merchant business withdrawal fee (Payout API + dashboard only)
    |--------------------------------------------------------------------------
    |
    | Consumer / WhatsApp wallet bank sends are not charged here.
    | Flat fee is debited from business balance on successful payout, in addition
    | to the withdrawal amount sent to the bank.
    */

    'fee_enabled' => filter_var(env('MERCHANT_WITHDRAWAL_FEE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'flat_fee' => max(0.0, (float) env('MERCHANT_WITHDRAWAL_FLAT_FEE', 50)),
];
