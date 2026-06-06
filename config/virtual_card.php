<?php

return [
    'enabled' => (bool) env('VIRTUAL_CARD_ENABLED', true),
    'request_fee_usd' => max(0.0, (float) env('VIRTUAL_CARD_REQUEST_FEE_USD', 5)),
    'fee_currency_from' => 'USD',
    'fee_currency_to' => 'NGN',
    'fx_mid_usd_ngn' => env('VIRTUAL_CARD_FX_MID_USD_NGN') !== null ? (float) env('VIRTUAL_CARD_FX_MID_USD_NGN') : null,
    'fx_sell_markup_percent' => max(0.0, (float) env('VIRTUAL_CARD_FX_SELL_MARKUP_PERCENT', 3)),
    'fx_buy_markup_percent' => max(0.0, (float) env('VIRTUAL_CARD_FX_BUY_MARKUP_PERCENT', 2)),
    'fx_sell_rate' => env('VIRTUAL_CARD_FX_SELL_RATE') !== null ? (float) env('VIRTUAL_CARD_FX_SELL_RATE') : null,
    'fx_buy_rate' => env('VIRTUAL_CARD_FX_BUY_RATE') !== null ? (float) env('VIRTUAL_CARD_FX_BUY_RATE') : null,
    'topup_min_usd' => max(0.01, (float) env('VIRTUAL_CARD_TOPUP_MIN_USD', 1)),
    'topup_max_usd' => max(1.0, (float) env('VIRTUAL_CARD_TOPUP_MAX_USD', 500)),
    'withdraw_min_usd' => max(0.01, (float) env('VIRTUAL_CARD_WITHDRAW_MIN_USD', 1)),
    'withdraw_max_usd' => max(1.0, (float) env('VIRTUAL_CARD_WITHDRAW_MAX_USD', 500)),
    'auto_fund_usd_enabled' => (bool) env('VIRTUAL_CARD_AUTO_FUND_USD_ENABLED', true),
    'auto_fund_usd_buffer' => max(0.0, (float) env('VIRTUAL_CARD_AUTO_FUND_USD_BUFFER', 1)),
    'auto_fund_usd_max_per_op' => max(0.0, (float) env('VIRTUAL_CARD_AUTO_FUND_USD_MAX_PER_OP', 500)),
    'auto_fund_ngn_per_usd' => max(1.0, (float) env('VIRTUAL_CARD_AUTO_FUND_NGN_PER_USD', 1400)),
    'auto_fund_ngn_buffer_percent' => max(0.0, (float) env('VIRTUAL_CARD_AUTO_FUND_NGN_BUFFER_PERCENT', 3)),
    'auto_fund_force_buy_usd' => max(0.01, (float) env('VIRTUAL_CARD_AUTO_FUND_FORCE_BUY_USD', 2)),
];
