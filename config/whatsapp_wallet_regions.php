<?php

/**
 * Evolution instance → region. Instance names should match Evolution + optional admin overrides (DB settings).
 */
return [
    'instances' => [
        env('WHATSAPP_EVOLUTION_INSTANCE', 'Whatsapp') => [
            'country' => 'NG',
            'currency' => 'NGN',
            'label' => 'Nigeria',
            'features' => ['p2p' => true, 'bank' => true, 'vtu' => true, 'rentals' => true],
        ],
        env('WHATSAPP_EVOLUTION_INSTANCE_NAMIBIA', 'Namibia') => [
            'country' => 'NA',
            'currency' => 'NAD',
            'label' => 'Namibia',
            'features' => ['p2p' => true, 'bank' => false, 'vtu' => false, 'rentals' => false],
        ],
    ],

    'unknown_instance_country' => strtoupper((string) env('WHATSAPP_WALLET_UNKNOWN_INSTANCE_COUNTRY', 'NG')),

    'country_by_dial' => [
        ['dial' => '234', 'country' => 'NG', 'currency' => 'NGN', 'label' => 'Nigeria'],
        ['dial' => '264', 'country' => 'NA', 'currency' => 'NAD', 'label' => 'Namibia'],
        ['dial' => '233', 'country' => 'GH', 'currency' => 'GHS', 'label' => 'Ghana'],
        ['dial' => '44', 'country' => 'GB', 'currency' => 'GBP', 'label' => 'United Kingdom'],
        // NANP (+1): currency resolved via nanp_canadian_npa (CAD) else USD
        ['dial' => '1', 'country' => 'US', 'currency' => 'USD', 'label' => 'United States / Canada (+1)'],
    ],

    /**
     * North American Numbering Plan area codes (NPA) assigned to Canada.
     * Used to choose CAD vs USD when the number is +1…
     *
     * @var list<int>
     */
    'nanp_canadian_npa' => [
        204, 226, 236, 249, 250, 263, 289, 306, 343, 354, 365, 367, 368, 382, 403, 416, 418, 428, 431, 437, 438, 450, 468, 474,
        506, 514, 519, 548, 579, 581, 584, 587, 604, 613, 639, 647, 672, 683, 705, 709, 742, 753, 778, 782, 807, 819, 825, 867,
        873, 879, 902, 905, 942, 958,
    ],
];
