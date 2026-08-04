<?php

/**
 * Server-side Nigerian bank account number prefixes for CheckoutNow fallback suggestions.
 *
 * The mobile app ships a built-in prefix table first; this config is used only when the app
 * calls GET /api/v1/rentals/banks/suggestions (no built-in match). Add rows here without an app rebuild.
 *
 * Each rule: longest matching prefix wins; order in this file is tie-break only.
 * `code` is a NIP / legacy bank code (normalized via NigerianBankCodeNormalizer).
 */
return [
    'max_suggestions' => 12,

    'rules' => [
        // OPay (100004 / legacy 305)
        ['prefix' => '802', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '803', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '806', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '807', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '808', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '809', 'code' => '100004', 'name' => 'OPay'],
        ['prefix' => '810', 'code' => '100004', 'name' => 'OPay'],

        // PalmPay (100033)
        ['prefix' => '801', 'code' => '100033', 'name' => 'PalmPay'],
        ['prefix' => '855', 'code' => '100033', 'name' => 'PalmPay'],

        // Kuda (090267)
        ['prefix' => '20', 'code' => '090267', 'name' => 'Kuda Bank'],

        // Moniepoint MFB (090405) — account series varies; extend as you observe live data
        ['prefix' => '540', 'code' => '090405', 'name' => 'Moniepoint MFB'],

        // FairMoney MFB (090551)
        ['prefix' => '7', 'code' => '090551', 'name' => 'FairMoney MFB'],

        // Sparkle (090325)
        ['prefix' => '90', 'code' => '090325', 'name' => 'Sparkle'],

        // VFD / VBank (090110)
        ['prefix' => '566', 'code' => '090110', 'name' => 'VFD / VBank'],
    ],
];
