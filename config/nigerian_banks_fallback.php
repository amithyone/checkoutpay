<?php

/**
 * Optional extra NIP rows merged when `php artisan banks:sync` runs and MevonPay getBankList fails.
 * Same shape as entries in config/banks.php: ['code' => '000014', 'name' => 'Access Bank'].
 * Leave empty to rely on config/banks.php only.
 */
return [];
