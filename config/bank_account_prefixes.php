<?php

/**
 * Server-side Nigerian bank account number prefixes for CheckoutNow suggestions.
 *
 * - GET /api/v1/rentals/banks/suggestions (no account) → full rules catalog for offline cache
 * - GET /api/v1/rentals/banks/suggestions?account=… → ordered banks for live fallback
 *
 * Same prefix may map to multiple banks. Longest matching prefix is ranked first.
 * `code` is a NIP / legacy bank code (normalized via NigerianBankCodeNormalizer).
 */
return [
    'max_suggestions' => 12,
    'default_category' => 'Fintech & Neo-Bank',

    /** When a bank payout succeeds, store first N digits of the destination NUBAN as a suggestion prefix. */
    'learn_from_transfers' => true,
    'learned_prefix_length' => 4,

    'rules' => [
        // OPay (100004 / legacy 305)
        ['prefix' => '70', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '71', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '80', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '81', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '90', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '91', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '802', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '803', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '804', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '805', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '806', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '807', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '808', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '809', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '810', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '811', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '812', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '813', 'code' => '100004', 'name' => 'OPay', 'category' => 'Fintech & Neo-Bank'],

        // PalmPay (100033) — shares some 80x series with OPay
        ['prefix' => '80', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '81', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '801', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '814', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '815', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '816', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '817', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '818', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '819', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '855', 'code' => '100033', 'name' => 'PalmPay', 'category' => 'Fintech & Neo-Bank'],

        // Kuda (090267)
        ['prefix' => '20', 'code' => '090267', 'name' => 'Kuda Bank', 'category' => 'Fintech & Neo-Bank'],

        // Moniepoint MFB (090405)
        ['prefix' => '540', 'code' => '090405', 'name' => 'Moniepoint MFB', 'category' => 'Fintech & Neo-Bank'],

        // FairMoney MFB (090551)
        ['prefix' => '70', 'code' => '090551', 'name' => 'FairMoney MFB', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '71', 'code' => '090551', 'name' => 'FairMoney MFB', 'category' => 'Fintech & Neo-Bank'],

        // Sparkle (090325)
        ['prefix' => '90', 'code' => '090325', 'name' => 'Sparkle', 'category' => 'Fintech & Neo-Bank'],
        ['prefix' => '91', 'code' => '090325', 'name' => 'Sparkle', 'category' => 'Fintech & Neo-Bank'],

        // VFD / VBank (090110)
        ['prefix' => '566', 'code' => '090110', 'name' => 'VFD / VBank', 'category' => 'Fintech & Neo-Bank'],
    ],
];
