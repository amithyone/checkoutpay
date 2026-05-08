<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MevonPayWebhookController;
use App\Http\Controllers\Api\LiveSyncReceiverController;
use App\Http\Controllers\Api\V1StatusController;
use App\Http\Controllers\Api\VtuWebhookController;
use App\Http\Controllers\Api\TagineBridgeController;
use App\Http\Controllers\Api\WhatsappWalletApiController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->middleware(\App\Http\Middleware\AuthenticateApiKey::class)->group(function () {
    // Payment routes (require API key)
    Route::post('/payment-request', [PaymentController::class, 'store']);
    Route::get('/payment/{transactionId}', [PaymentController::class, 'show']);
    // Correct wrong amount: updates payment, recalculates charges, dispatches CheckPaymentEmails to re-scan
    // emails with new amount; when an email matches, payment is approved and the same webhook is sent
    // (payment.approved, unchanged payload). Only pending, non-expired payments can be updated.
    Route::patch('/payment/{transactionId}/amount', [PaymentController::class, 'updateAmount']);
    Route::get('/payments', [PaymentController::class, 'index']);

    // Withdrawal routes (require API key)
    Route::post('/withdrawal', [\App\Http\Controllers\Api\WithdrawalController::class, 'store']);
    Route::get('/withdrawals', [\App\Http\Controllers\Api\WithdrawalController::class, 'index']);
    Route::get('/balance', [\App\Http\Controllers\Api\WithdrawalController::class, 'balance']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/whatsapp-wallet/lookup', [WhatsappWalletApiController::class, 'lookup']);
        Route::post('/whatsapp-wallet/ensure', [WhatsappWalletApiController::class, 'ensure']);
        Route::post('/whatsapp-wallet/send-message', [WhatsappWalletApiController::class, 'sendMessage']);
        Route::post('/whatsapp-wallet/topup/virtual-account', [WhatsappWalletApiController::class, 'issueTopupVirtualAccount']);
        Route::post('/whatsapp-wallet/pay/start', [WhatsappWalletApiController::class, 'startPartnerPay']);
    });
});

// Public routes (no API key required)
Route::prefix('v1')->group(function () {
    // GET /api/v1 — JSON status; webhook_base_url from WHATSAPP_APP_URL or APP_URL (see config/whatsapp.php)
    Route::get('/', V1StatusController::class)->name('api.v1.status');

    // Secure inbound sync receiver (live site -> this app)
    Route::post('sync/live', [LiveSyncReceiverController::class, 'receive'])
        ->middleware([\App\Http\Middleware\VerifyLiveSyncSignature::class, 'throttle:120,1']);

    // Statistics routes
    Route::get('/statistics', [\App\Http\Controllers\Api\StatisticsController::class, 'index']);

    // Email webhook (for email forwarding services like Zapier)
    Route::post('/email/webhook', [\App\Http\Controllers\Api\EmailWebhookController::class, 'receive']);
    Route::get('/email/webhook', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck']); // GET for health checks and testing
    Route::post('/webhook/email', [\App\Http\Controllers\Api\EmailWebhookController::class, 'receive']); // Legacy route for backward compatibility
    Route::get('/webhook/email', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck']); // GET for legacy route too
    Route::get('/email/webhook/health', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck'])->name('email.webhook.health');

    // Transaction check endpoint (for external sites to trigger email checking)
    Route::post('/transaction/check', [\App\Http\Controllers\Api\TransactionCheckController::class, 'checkTransaction']);
    Route::get('/transaction/check', [\App\Http\Controllers\Api\TransactionCheckController::class, 'checkTransaction']); // Also support GET

    // Webhook processing cron endpoint (for external cron services)
    Route::get('/cron/process-webhooks', [\App\Http\Controllers\Cron\WebhookCronController::class, 'processWebhooks']);
    Route::post('/cron/process-webhooks', [\App\Http\Controllers\Cron\WebhookCronController::class, 'processWebhooks']); // Also support POST

    // MEVONPAY external funding webhook (account_number is source of truth)
    Route::post('/webhook/mevonpay', [MevonPayWebhookController::class, 'receive']);
    Route::post('/webhooks/mevonpay', [MevonPayWebhookController::class, 'receive']); // plural alias
    Route::post('/webhook/sla', [MevonPayWebhookController::class, 'receive']); // backward compatibility
    Route::post('/webhooks/sla', [MevonPayWebhookController::class, 'receive']); // plural alias
    Route::post('/webhook/mavonpay', [MevonPayWebhookController::class, 'receive']); // backward compatibility
    Route::post('/webhooks/mavonpay', [MevonPayWebhookController::class, 'receive']); // plural alias

    // VTU provider webhook (reversals/refunds/status updates)
    Route::post('/webhook/vtu', [VtuWebhookController::class, 'receive'])->middleware('throttle:120,1');
    Route::post('/webhooks/vtu', [VtuWebhookController::class, 'receive'])->middleware('throttle:120,1');

    /**
     * Tax Calculator open API
     */
    Route::prefix('tax')->group(function () {
        Route::post('business', [\App\Http\Controllers\Api\TaxController::class, 'saveBusiness']);
        Route::post('personal', [\App\Http\Controllers\Api\TaxController::class, 'savePersonal']);
    });

    Route::post('nigtax/visit', [\App\Http\Controllers\Api\NigtaxVisitController::class, 'store'])
        ->middleware('throttle:120,1');

    /**
     * Evolution API → Checkout (OTP link for renters). Configure webhook to POST here.
     * Header X-Checkout-WhatsApp-Secret or ?secret= when WHATSAPP_WEBHOOK_SECRET is set.
     */
    Route::post('whatsapp/webhook', [WhatsappWebhookController::class, 'receive'])
        ->middleware('throttle:120,1');
    Route::get('whatsapp/webhook', [WhatsappWebhookController::class, 'health']);

    Route::prefix('nigtax/certified')->middleware('throttle:60,1')->group(function () {
        Route::get('settings', [\App\Http\Controllers\Api\NigtaxCertifiedPublicController::class, 'settings']);
        Route::post('orders', [\App\Http\Controllers\Api\NigtaxCertifiedPublicController::class, 'store']);
        Route::get('orders/{transaction_id}', [\App\Http\Controllers\Api\NigtaxCertifiedPublicController::class, 'show']);
    });

    Route::prefix('nigtax/pro')->middleware('throttle:60,1')->group(function () {
        Route::get('config', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'config']);
        Route::post('password/email', [\App\Http\Controllers\Api\NigtaxProPasswordResetController::class, 'sendResetLinkEmail'])
            ->middleware('throttle:6,1');
        Route::post('membership/checkout', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'startMembershipCheckout'])
            ->middleware('throttle:10,1');
        Route::get('membership/payment/{transaction_id}', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'membershipPaymentStatus']);
        Route::post('register', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'register'])
            ->middleware('throttle:10,1');
        Route::post('login', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'login'])
            ->middleware('throttle:20,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'me']);
            Route::post('logout', [\App\Http\Controllers\Api\NigtaxProAuthController::class, 'logout']);
            Route::get('queries', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'index']);
            Route::post('queries', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::get('queries/{id}', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'show'])->whereNumber('id');
            Route::get('queries/{id}/statement', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'downloadStatement'])->whereNumber('id');
            Route::delete('queries/{id}/statement', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'destroyStatement'])->whereNumber('id');
            Route::delete('queries/{id}', [\App\Http\Controllers\Api\NigtaxProQueryController::class, 'destroy'])->whereNumber('id');
        });
    });

    /**
     * Rentals public API (no auth required)
     */
    Route::prefix('rentals')->group(function () {
        // Catalog
        Route::get('categories', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'categories']);
        Route::get('items', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'index']);
        Route::get('items/{slug}', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'show']);
        Route::get('items/{id}/unavailable-dates', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'unavailableDates'])
            ->whereNumber('id');

        // KYC verification (public AJAX-style endpoint)
        Route::post('kyc/verify', [\App\Http\Controllers\Api\Rentals\KycController::class, 'verify']);
        // Dynamic possible banks for an account number (NUBAN-backed)
        Route::post('kyc/banks', [\App\Http\Controllers\Api\Rentals\KycController::class, 'banksForAccount']);
        // All known banks from Checkout DB (cached from NUBAN responses)
        Route::get('banks', [\App\Http\Controllers\Api\Rentals\KycController::class, 'banksFromDatabase']);

        // Renter auth (token-based)
        Route::post('auth/register', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'register']);
        Route::post('auth/login', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'login']);

        // Forgot password – send reset link email
        Route::post('password/email', [\App\Http\Controllers\Api\Rentals\PasswordResetController::class, 'sendResetLinkEmail']);
    });
});

/**
 * Rentals authenticated API (requires Sanctum token, authenticating Renter model)
 */
Route::prefix('v1/rentals')
    ->middleware(['auth:sanctum', 'renter_active'])
    ->group(function () {
        // Current renter
        Route::get('me', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'me']);
        Route::post('auth/logout', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'logout']);
        Route::post('me/email/resend-verification', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'resendEmailVerification']);
        Route::post('me/email/verify-pin', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'verifyEmailPin']);

        // KYC update for renter
        Route::post('me/kyc', [\App\Http\Controllers\Api\Rentals\KycController::class, 'update']);
        Route::post('me/kyc-id', [\App\Http\Controllers\Api\Rentals\KycController::class, 'uploadId']);

        // Checkout flow
        Route::post('checkout/quote', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'quote']);
        Route::post('checkout/submit', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'submit']);

        // Account management
        Route::post('password/change', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'changePassword']);
        Route::get('wallet', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'wallet']);
        Route::post('devices/register', [\App\Http\Controllers\Api\Rentals\DeviceController::class, 'register']);
        Route::get('support/messages', [\App\Http\Controllers\Api\Rentals\SupportController::class, 'messages']);
        Route::post('wallet/fund', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'fundWallet']);
        Route::post('wallet/fund/check', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'checkWalletFunding']);
        Route::post('me/profile', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'updateProfile']);

        // Renter rentals
        Route::get('requests', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'listRentals']);
        Route::get('requests/{rental}', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'showRental'])
            ->whereNumber('rental');
        Route::post('requests/{rental}/check-payment', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'checkPayment'])
            ->whereNumber('rental');
        Route::post('requests/{rental}/request-return', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'requestReturn'])
            ->whereNumber('rental');
        Route::post('requests/{rental}/fulfillment', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'setFulfillment'])
            ->whereNumber('rental');
        Route::post('requests/{rental}/return-method', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'setReturnMethod'])
            ->whereNumber('rental');

        /**
         * Business management (authenticated via rentals token + email→Business)
         */
        Route::get('business/summary', \App\Http\Controllers\Api\Rentals\Business\SummaryController::class);
        Route::get('business/rentals', [\App\Http\Controllers\Api\Rentals\Business\RentalsController::class, 'index']);
        Route::get('business/rentals/{rental}', [\App\Http\Controllers\Api\Rentals\Business\RentalsController::class, 'show'])
            ->whereNumber('rental');
        Route::post('business/rentals/{rental}/mark-picked-up', [\App\Http\Controllers\Api\Rentals\Business\RentalsController::class, 'markPickedUp'])
            ->whereNumber('rental');
        Route::post('business/rentals/{rental}/confirm-return', [\App\Http\Controllers\Api\Rentals\Business\RentalsController::class, 'confirmReturn'])
            ->whereNumber('rental');
        Route::get('business/items', [\App\Http\Controllers\Api\Rentals\Business\ItemsController::class, 'index']);
        Route::post('business/items', [\App\Http\Controllers\Api\Rentals\Business\ItemsController::class, 'store']);
        // POST included so multipart updates can use _method=PATCH (some proxies strip PATCH bodies).
        Route::match(['patch', 'post'], 'business/items/{item}', [\App\Http\Controllers\Api\Rentals\Business\ItemsController::class, 'update'])
            ->whereNumber('item');
        Route::get('business/withdrawals', [\App\Http\Controllers\Api\Rentals\Business\WithdrawalsController::class, 'index']);
        Route::post('business/withdrawals', [\App\Http\Controllers\Api\Rentals\Business\WithdrawalsController::class, 'store']);
        Route::get('business/withdrawal-accounts', [\App\Http\Controllers\Api\Rentals\Business\WithdrawalAccountsController::class, 'index']);
        Route::post('business/withdrawal-accounts', [\App\Http\Controllers\Api\Rentals\Business\WithdrawalAccountsController::class, 'store']);
        Route::get('business/settings', [\App\Http\Controllers\Api\Rentals\Business\SettingsController::class, 'show']);
        Route::patch('business/settings', [\App\Http\Controllers\Api\Rentals\Business\SettingsController::class, 'update']);
        Route::post('business/settings/withdrawal-pin', [\App\Http\Controllers\Api\Rentals\Business\SettingsController::class, 'setWithdrawalPin']);
        Route::post('business/settings/withdrawal-pin/verify', [\App\Http\Controllers\Api\Rentals\Business\SettingsController::class, 'verifyWithdrawalPin']);
    });

/**
 * NigTax admin API (Sanctum token; same Admin accounts as checkout, roles: tax, super_admin)
 */
Route::prefix('v1/tax-admin')->group(function () {
    Route::get('/login', function () {
        return response()->json([
            'message' => 'Use POST with JSON body: {"email":"...","password":"..."}. Roles allowed: tax, super_admin.',
        ]);
    });
    Route::post('/login', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'tax_admin_api'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'logout']);
        Route::get('/user', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'user']);
        Route::get('/stats', [\App\Http\Controllers\Api\TaxAdminStatsController::class, 'index']);
        Route::put('/password', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'changePassword']);
        Route::get('/business-records', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'businessRecords']);
        Route::get('/personal-records', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'personalRecords']);
        Route::get('/pro-users', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'proUsers']);

        Route::get('/certified/settings', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'certifiedSettings']);
        Route::put('/certified/settings', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'certifiedSettingsUpdate']);
        Route::get('/certified/consultants', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsIndex']);
        Route::post('/certified/consultants', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsStore']);
        Route::put('/certified/consultants/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsUpdate'])
            ->whereNumber('id');
        Route::delete('/certified/consultants/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsDestroy'])
            ->whereNumber('id');
        Route::post('/certified/consultants/{id}/signature', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadConsultantSignature'])
            ->whereNumber('id');
        Route::post('/certified/consultants/{id}/stamp', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadConsultantStamp'])
            ->whereNumber('id');
        Route::get('/certified/consultant', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantShow']);
        Route::put('/certified/consultant', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantUpdate']);
        Route::post('/certified/consultant/signature', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadSignature']);
        Route::post('/certified/consultant/stamp', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadStamp']);
        Route::get('/certified/orders', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'ordersIndex']);
        Route::get('/certified/orders/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'orderShow'])
            ->whereNumber('id');
        Route::patch('/certified/orders/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'orderUpdate'])
            ->whereNumber('id');
    });
});

/*
| Tagine ↔ Checkout bridge (shared secret). Tagine generates OTP locally; Checkout only sends WhatsApp + stores wallet.
*/
Route::middleware(['throttle:12,1', 'tagine.otp.secret'])
    ->prefix('tagine')
    ->group(function () {
        Route::post('whatsapp/send-text', [TagineBridgeController::class, 'sendWhatsAppText']);
        Route::post('wallet/ensure', [TagineBridgeController::class, 'ensureWallet']);
    });

/*
| Amithy DRM desktop telemetry + policy webhook (HMAC + bearer)
| Bearer maps to either a desktop_app_tokens row or env AMITHY_API_TOKEN.
*/
Route::prefix('desktop')->middleware('throttle:120,1')->group(function () {
    Route::post('/events/batch', [\App\Http\Controllers\Api\DesktopTelemetryController::class, 'ingestBatch']);
    Route::get('/policy', [\App\Http\Controllers\Api\DesktopTelemetryController::class, 'getPolicy']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Email Payment Gateway',
    ]);
});
