<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transaction Transfer Feature
    |--------------------------------------------------------------------------
    |
    | This setting controls whether super admins can transfer transactions
    | from businesses to the super admin business.
    |
    | Set to true to enable, false to disable.
    | Can also be controlled via database setting 'transaction_transfer_enabled'
    |
    */

    'enabled' => env('TRANSACTION_TRANSFER_ENABLED', true),
];
