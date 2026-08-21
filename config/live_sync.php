<?php

use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\BusinessActivityLog;
use App\Models\BusinessDisbursementBatch;
use App\Models\BusinessDisbursementItem;
use App\Models\BusinessEmployee;
use App\Models\BusinessTransaction;
use App\Models\BusinessWebsite;
use App\Models\BusinessWithdrawalAccount;
use App\Models\MevonPayLedgerEntry;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\VirtualCardRequest;
use App\Models\WalletSavingsGoal;
use App\Models\WalletSavingsLock;
use App\Models\WalletSavingsSetting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\WithdrawalRequest;

/**
 * Common money-path entities Namecheap → Contabo.
 * natural_key: unique business key when available (preferred over origin id map).
 * exclude: never sync secrets / local-only columns.
 */
return [
    /**
     * Push order for --entity=common / all (dependencies first).
     */
    'common_order' => [
        'renter',
        'business',
        'business_website',
        'payment',
        'account_number',
        'whatsapp_wallet',
        'whatsapp_wallet_transaction',
        'withdrawal_request',
        'business_transaction',
        'business_activity_log',
        'business_withdrawal_account',
        'business_employee',
        'business_disbursement_batch',
        'business_disbursement_item',
        'virtual_card_request',
        'mevon_pay_ledger_entry',
        'wallet_savings_setting',
        'wallet_savings_goal',
        'wallet_savings_lock',
    ],

    'entities' => [
        'payment' => [
            'model' => Payment::class,
            'natural_key' => ['transaction_id'],
            'exclude' => ['id', 'deleted_at'],
            'observe' => true,
        ],
        'business' => [
            'model' => Business::class,
            'natural_key' => ['business_id'],
            'fallback_natural_key' => ['email'],
            'exclude' => ['id', 'password', 'remember_token', 'api_token', 'api_secret'],
            'observe' => true,
        ],
        'renter' => [
            'model' => Renter::class,
            'natural_key' => ['email'],
            'exclude' => ['id', 'password', 'remember_token'],
            'observe' => true,
        ],
        'business_website' => [
            'model' => BusinessWebsite::class,
            'natural_key' => [],
            'exclude' => ['id'],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'extras' => ['business_code' => 'business.business_id'],
            'observe' => true,
        ],
        'account_number' => [
            'model' => AccountNumber::class,
            'natural_key' => ['account_number'],
            'exclude' => ['id'],
            'observe' => true,
        ],
        'whatsapp_wallet' => [
            'model' => WhatsappWallet::class,
            'natural_key' => ['phone_e164'],
            'exclude' => [
                'id', 'pin_hash', 'pin_failed_attempts', 'pin_locked_until',
                'pin_set_at',
            ],
            'extras' => [
                'renter_email' => 'renter.email',
                'linked_business_code' => 'linkedBusiness.business_id',
            ],
            'resolve' => [
                'renter_id' => ['entity' => 'renter', 'via' => 'renter_email', 'attr' => 'email'],
                'linked_business_id' => ['entity' => 'business', 'via' => 'linked_business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'whatsapp_wallet_transaction' => [
            'model' => WhatsappWalletTransaction::class,
            'natural_key' => ['external_reference'], // empty refs fall back to origin map
            'exclude' => ['id', 'whatsapp_wallet_id'],
            'extras' => [
                'wallet_phone' => 'wallet.phone_e164',
            ],
            'resolve' => [
                'whatsapp_wallet_id' => ['entity' => 'whatsapp_wallet', 'via' => 'wallet_phone', 'attr' => 'phone_e164'],
            ],
            'observe' => true,
        ],
        'withdrawal_request' => [
            'model' => WithdrawalRequest::class,
            'natural_key' => ['payout_reference'],
            'exclude' => ['id', 'business_id', 'processed_by', 'payout_raw_response', 'deleted_at'],
            'extras' => [
                'business_code' => 'business.business_id',
            ],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'business_transaction' => [
            'model' => BusinessTransaction::class,
            'natural_key' => ['reference'],
            'exclude' => ['id', 'business_id', 'payment_id', 'counterparty_business_id', 'business_loan_ledger_entry_id'],
            'extras' => [
                'business_code' => 'business.business_id',
                'payment_transaction_id' => 'payment.transaction_id',
            ],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
                'payment_id' => ['entity' => 'payment', 'via' => 'payment_transaction_id', 'attr' => 'transaction_id'],
            ],
            'observe' => true,
        ],
        'business_activity_log' => [
            'model' => BusinessActivityLog::class,
            'natural_key' => [], // origin map only
            'exclude' => ['id', 'business_id'],
            'extras' => [
                'business_code' => 'business.business_id',
            ],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'business_withdrawal_account' => [
            'model' => BusinessWithdrawalAccount::class,
            'natural_key' => [],
            'exclude' => ['id', 'business_id'],
            'extras' => ['business_code' => 'business.business_id'],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'business_employee' => [
            'model' => BusinessEmployee::class,
            'natural_key' => [],
            'exclude' => ['id', 'business_id'],
            'extras' => ['business_code' => 'business.business_id'],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'business_disbursement_batch' => [
            'model' => BusinessDisbursementBatch::class,
            'natural_key' => [],
            'exclude' => ['id', 'business_id'],
            'extras' => ['business_code' => 'business.business_id'],
            'resolve' => [
                'business_id' => ['entity' => 'business', 'via' => 'business_code', 'attr' => 'business_id'],
            ],
            'observe' => true,
        ],
        'business_disbursement_item' => [
            'model' => BusinessDisbursementItem::class,
            'natural_key' => ['provider_reference'],
            'exclude' => ['id', 'batch_id', 'business_employee_id'],
            'extras' => [
                'batch_origin_id' => 'batch.id',
            ],
            'resolve' => [
                'batch_id' => ['entity' => 'business_disbursement_batch', 'via' => 'batch_origin_id', 'origin' => true],
            ],
            'observe' => true,
        ],
        'virtual_card_request' => [
            'model' => VirtualCardRequest::class,
            'natural_key' => [],
            'exclude' => ['id', 'whatsapp_wallet_id'],
            'extras' => ['wallet_phone' => 'wallet.phone_e164'],
            'resolve' => [
                'whatsapp_wallet_id' => ['entity' => 'whatsapp_wallet', 'via' => 'wallet_phone', 'attr' => 'phone_e164'],
            ],
            'observe' => true,
        ],
        'mevon_pay_ledger_entry' => [
            'model' => MevonPayLedgerEntry::class,
            'natural_key' => [], // prefer external_reference / payout_reference in serializer
            'exclude' => ['id', 'source_id'],
            'observe' => true,
        ],
        'wallet_savings_setting' => [
            'model' => WalletSavingsSetting::class,
            'natural_key' => [],
            'exclude' => ['id', 'whatsapp_wallet_id'],
            'extras' => ['wallet_phone' => 'wallet.phone_e164'],
            'resolve' => [
                'whatsapp_wallet_id' => ['entity' => 'whatsapp_wallet', 'via' => 'wallet_phone', 'attr' => 'phone_e164'],
            ],
            'observe' => true,
        ],
        'wallet_savings_goal' => [
            'model' => WalletSavingsGoal::class,
            'natural_key' => [],
            'exclude' => ['id', 'whatsapp_wallet_id'],
            'extras' => ['wallet_phone' => 'wallet.phone_e164'],
            'resolve' => [
                'whatsapp_wallet_id' => ['entity' => 'whatsapp_wallet', 'via' => 'wallet_phone', 'attr' => 'phone_e164'],
            ],
            'observe' => true,
        ],
        'wallet_savings_lock' => [
            'model' => WalletSavingsLock::class,
            'natural_key' => [],
            'exclude' => ['id', 'whatsapp_wallet_id'],
            'extras' => ['wallet_phone' => 'wallet.phone_e164'],
            'resolve' => [
                'whatsapp_wallet_id' => ['entity' => 'whatsapp_wallet', 'via' => 'wallet_phone', 'attr' => 'phone_e164'],
            ],
            'observe' => true,
        ],
    ],
];
