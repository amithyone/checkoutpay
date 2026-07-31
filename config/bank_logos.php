<?php

/**
 * Local bank logo library (vendored SVGs under resources/bank-logos/library).
 * Mapping never deletes banks — it only sets logo_path when empty (or --force).
 */
return [
    'disk' => 'public',
    'directory' => 'bank-logos',
    'library_path' => resource_path('bank-logos/library'),
    'cache_key' => 'banks:list:with-logos:v2',

    /**
     * NIP 6-digit code => library filename.
     */
    'by_code' => [
        '000014' => 'accesscorp.svg', // Access
        '000013' => 'gtco.svg', // GTBank / GTCO
        '000015' => 'zenithbank.svg',
        '000004' => 'uba.svg',
        '000016' => 'firstholdco.svg', // First Bank / FBN
        '000007' => 'fidelity.svg',
        '000012' => 'stanbic.svg',
        '000010' => 'eti.svg', // Ecobank
        '000001' => 'sterlingng.svg',
        '000017' => 'wemabank.svg',
        '000003' => 'fcmb.svg',
        '000006' => 'jaizbank.svg',
        '000011' => 'unitybnk.svg',
        // Union Bank (000018): no dedicated SVG in library — upload manually
    ],

    /**
     * Normalized name substring => library filename (fallback when code unknown).
     * Longer / more specific keys should be listed first where overlap matters.
     */
    'by_name_contains' => [
        'access bank' => 'accesscorp.svg',
        'access holdings' => 'accesscorp.svg',
        'guaranty trust' => 'gtco.svg',
        'gtbank' => 'gtco.svg',
        'gtb' => 'gtco.svg',
        'gtco' => 'gtco.svg',
        'zenith' => 'zenithbank.svg',
        'united bank for africa' => 'uba.svg',
        'uba' => 'uba.svg',
        'first bank' => 'firstholdco.svg',
        'firstbank' => 'firstholdco.svg',
        'fbn holdings' => 'firstholdco.svg',
        'fidelity' => 'fidelity.svg',
        'stanbic' => 'stanbic.svg',
        'ecobank' => 'eti.svg',
        'sterling' => 'sterlingng.svg',
        'wema' => 'wemabank.svg',
        'fcmb' => 'fcmb.svg',
        'first city monument' => 'fcmb.svg',
        'jaiz' => 'jaizbank.svg',
        'unity bank' => 'unitybnk.svg',
        'abbey mortgage' => 'abbeybds.svg',
        'aso savings' => 'asosavings.svg',
        'livingtrust' => 'livingtrust.svg',
        'living trust' => 'livingtrust.svg',
        'npf microfinance' => 'npfmcrfbk.svg',
        'npf mfb' => 'npfmcrfbk.svg',
    ],
];
