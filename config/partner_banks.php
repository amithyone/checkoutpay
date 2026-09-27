<?php

/**
 * Partner-readable bank rail registry. Adapter implementations ship ionCube-encoded in partner releases.
 */
return [
    'default' => env('PARTNER_BANK_DEFAULT', 'mevonpay'),

    /**
     * Slugs enabled for this deployment (must match adapters shipped in the encoded build).
     *
     * @var list<string>
     */
    'enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PARTNER_BANKS_ENABLED', 'mevonpay'))
    ))),
];
