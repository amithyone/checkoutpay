<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tagine restaurant app — server-to-server bridge
    |--------------------------------------------------------------------------
    |
    | Tagine backend calls these endpoints with header X-Tagine-Otp-Secret (shared secret).
    |
    | - whatsapp/send-text: deliver a pre-built message via Evolution only (no OTP logic here).
    | - wallet/ensure: create or load a WhatsappWallet for the verified phone (Checkout is source of truth).
    |
    | Optional TAGINE_WALLET_RENTER_ID links new wallets to your Checkout renter (Tagine merchant) for pay-ins.
    |
    */

    'secret' => env('TAGINE_OTP_SECRET', ''),

    'wallet_renter_id' => env('TAGINE_WALLET_RENTER_ID') !== null && env('TAGINE_WALLET_RENTER_ID') !== ''
        ? (int) env('TAGINE_WALLET_RENTER_ID')
        : null,
];
