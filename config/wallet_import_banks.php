<?php

/**
 * Messy form-CSV bank labels → NIP / CBN bank codes.
 * Keys are normalized (lowercase, no punctuation, optional "bank"/"plc" stripped by resolver).
 */
return [
    /*
    | Canonical short aliases (after FormCsvBankResolver::normalizeLabel).
    | Prefer classic 3-digit NIP codes where Mevon/NUBAN accept them.
    */
    'aliases' => [
        // Values are classic codes; FormCsvBankResolver runs NigerianBankCodeNormalizer.
        'uba' => '033', // → NIP 000004
        'unitedbankofafrica' => '033',
        'unitedbankforafrica' => '033',
        'unitedbankofafricanuba' => '033',
        'unitedbankforafricanuba' => '033',

        'gtb' => '058', // → NIP 000013
        'gtbank' => '058',
        'gt' => '058',
        'guarantytrust' => '058',
        'guaranteetrust' => '058',
        'guarantytrustbank' => '058',

        'access' => '044',
        'accessbank' => '044',
        'acess' => '044',
        'acessbank' => '044',
        'diamond' => '044',
        'diamondaccess' => '044',
        'accessdiamond' => '044',

        'first' => '011',
        'firstbank' => '011',
        '1stbank' => '011',
        'fristbank' => '011',
        'firstbankplc' => '011',

        'zenith' => '057',
        'zenithbank' => '057',

        'fcmb' => '214',
        'fcmbbank' => '214',

        'union' => '032',
        'unionbank' => '032',

        'fidelity' => '070',
        'fidelitybank' => '070',

        'eco' => '050',
        'ecobank' => '050',

        'polaris' => '076',
        'polarisbank' => '076',

        'unity' => '215',
        'unitybank' => '215',
        'unitybankplc' => '215',

        'sterling' => '232',
        'sterlingbank' => '232',

        'wema' => '035',
        'wemabank' => '035',

        'stanbic' => '221',
        'stanbicibtc' => '221',
        'stanbicibtcbank' => '221',

        'keystone' => '082',
        'keystonebank' => '082',

        'heritage' => '030',
        'heritagebank' => '030',

        'jaiz' => '301',
        'jaizbank' => '301',

        'opay' => '100004',
        'palmpay' => '100033',
        'kuda' => '090267',
        'kudamfb' => '090267',
        'moniepoint' => '090405',
        'moniepointmfb' => '090405',
    ],

    /** Seconds to cache name-enquiry results during sterilize (re-runs). */
    'name_enquiry_cache_seconds' => 604800, // 7 days

    /** Delay between live name-enquiry calls (ms). */
    'name_enquiry_delay_ms' => 150,
];
