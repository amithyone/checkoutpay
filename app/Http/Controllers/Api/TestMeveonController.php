<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MavonPayTransferService;
use App\Services\MevonPayBankService;
use App\Services\MevonPayVirtualAccountService;
use App\Services\MevonRubiesVirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TestMeveonController extends Controller
{
    public function __construct(
        private MevonPayVirtualAccountService $vaService,
        private MevonPayBankService $bankService,
        private MavonPayTransferService $transferService,
        private MevonRubiesVirtualAccountService $rubiesService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $validated = $request->validate([
            'action' => 'required|string|in:ping,createtempva,createdynamic,getBankList,nameEnquiry,createtransfer,createrubies_personal,createrubies_business,rubies_electricity_getInfo,rubies_electricity_verify,rubies_electricity_buy,rubies_cable_getInfo,rubies_cable_verify,rubies_cable_buy',
        ]);

        $action = (string) $validated['action'];

        return match ($action) {
            'ping' => $this->ping(),
            'createtempva' => $this->createTempVa($request),
            'createdynamic' => $this->createDynamicVa($request),
            'getBankList' => $this->getBankList(),
            'nameEnquiry' => $this->nameEnquiry($request),
            'createtransfer' => $this->createTransfer($request),
            'createrubies_personal' => $this->createRubiesPersonal($request),
            'createrubies_business' => $this->createRubiesBusiness($request),
            'rubies_electricity_getInfo' => $this->rubiesElectricityGetInfo(),
            'rubies_electricity_verify' => $this->rubiesElectricityVerify($request),
            'rubies_electricity_buy' => $this->rubiesElectricityBuy($request),
            'rubies_cable_getInfo' => $this->rubiesCableGetInfo(),
            'rubies_cable_verify' => $this->rubiesCableVerify($request),
            'rubies_cable_buy' => $this->rubiesCableBuy($request),
            default => response()->json([
                'success' => false,
                'message' => 'Unsupported action',
            ], 422),
        };
    }

    private function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'endpoint' => 'test-meveon',
            'provider' => 'mevonpay',
            'configured' => [
                'virtual_accounts' => $this->vaService->isConfigured(),
                'bank_service' => $this->bankService->isConfigured(),
                'transfer_service' => $this->transferService->isConfigured(),
            ],
            'supported_actions' => [
                'ping',
                'createtempva',
                'createdynamic',
                'getBankList',
                'nameEnquiry',
                'createtransfer',
                'createrubies_personal',
                'createrubies_business',
                'rubies_electricity_getInfo',
                'rubies_electricity_verify',
                'rubies_electricity_buy',
                'rubies_cable_getInfo',
                'rubies_cable_verify',
                'rubies_cable_buy',
            ],
        ]);
    }

    private function createTempVa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'registration_number' => 'nullable|string|max:50',
            'bvn' => 'nullable|string|max:30',
        ]);

        $registration = trim((string) ($validated['registration_number'] ?? ''));
        $bvn = trim((string) ($validated['bvn'] ?? ''));
        if ($registration === '' && $bvn === '') {
            return response()->json([
                'success' => false,
                'message' => 'Provide registration_number (preferred) or bvn.',
            ], 422);
        }

        $result = $this->vaService->createTempVa(
            (string) $validated['fname'],
            (string) $validated['lname'],
            $registration !== '' ? $registration : null,
            $bvn !== '' ? $bvn : null
        );

        return response()->json([
            'success' => true,
            'action' => 'createtempva',
            'provider_endpoint' => '/V1/createtempva.php',
            'data' => $result,
        ]);
    }

    private function createDynamicVa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
        ]);

        $result = $this->vaService->createDynamicVa(
            (float) $validated['amount'],
            (string) ($validated['currency'] ?? 'NGN')
        );

        return response()->json([
            'success' => true,
            'action' => 'createdynamic',
            'provider_endpoint' => '/V1/createdynamic',
            'data' => $result,
        ]);
    }

    private function getBankList(): JsonResponse
    {
        $rows = $this->bankService->getBankList();
        if ($rows === null) {
            return response()->json([
                'success' => false,
                'action' => 'getBankList',
                'provider_endpoint' => '/V1/bank_service',
                'message' => 'Failed to fetch bank list from MevonPay.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'action' => 'getBankList',
            'provider_endpoint' => '/V1/bank_service',
            'count' => count($rows),
            'data' => $rows,
        ]);
    }

    private function nameEnquiry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bankCode' => 'required|string|max:20',
            'accountNumber' => 'required|string|size:10',
        ]);

        $result = $this->bankService->nameEnquiry(
            (string) $validated['bankCode'],
            (string) $validated['accountNumber']
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'action' => 'nameEnquiry',
                'provider_endpoint' => '/V1/bank_service',
                'message' => 'Name enquiry failed or returned empty response.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'action' => 'nameEnquiry',
            'provider_endpoint' => '/V1/bank_service',
            'data' => $result,
        ]);
    }

    private function createTransfer(Request $request): JsonResponse
    {
        $allowTransfer = (bool) config('services.mevonpay.test_allow_transfer', false);
        if (! $allowTransfer) {
            return response()->json([
                'success' => false,
                'action' => 'createtransfer',
                'message' => 'Transfer testing is disabled. Set MEVONPAY_TEST_ALLOW_TRANSFER=true to enable.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'bankCode' => 'required|string|max:20',
            'bankName' => 'required|string|max:255',
            'creditAccountName' => 'required|string|max:255',
            'creditAccountNumber' => 'required|string|size:10',
            'narration' => 'nullable|string|max:255',
            'reference' => 'required|string|max:100',
            'sessionId' => 'nullable|string|max:100',
            'confirm_live_transfer' => 'required|boolean',
        ]);

        if (! (bool) $validated['confirm_live_transfer']) {
            return response()->json([
                'success' => false,
                'action' => 'createtransfer',
                'message' => 'Set confirm_live_transfer=true to run a real transfer test.',
            ], 422);
        }

        $result = $this->transferService->createTransfer([
            'amount' => $validated['amount'],
            'bankCode' => $validated['bankCode'],
            'bankName' => $validated['bankName'],
            'creditAccountName' => $validated['creditAccountName'],
            'creditAccountNumber' => $validated['creditAccountNumber'],
            'narration' => $validated['narration'] ?? 'MevonPay documentation test transfer',
            'reference' => $validated['reference'],
            'sessionId' => $validated['sessionId'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'action' => 'createtransfer',
            'provider_endpoint' => '/V1/createtransfer',
            'data' => $result,
        ]);
    }

    private function createRubiesPersonal(Request $request): JsonResponse
    {
        if (! $this->rubiesService->isConfigured()) {
            return response()->json([
                'success' => false,
                'action' => 'createrubies_personal',
                'message' => 'Mevon Rubies is not configured (base_url/secret_key missing).',
            ], 422);
        }

        $validated = $request->validate([
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|max:255',
            'bvn' => 'nullable|string|max:30',
            'nin' => 'nullable|string|max:30',
        ]);

        $bvn = trim((string) ($validated['bvn'] ?? ''));
        $nin = trim((string) ($validated['nin'] ?? ''));
        if ($bvn === '' && $nin === '') {
            return response()->json([
                'success' => false,
                'action' => 'createrubies_personal',
                'message' => 'Provide bvn or nin for personal Rubies account creation.',
            ], 422);
        }

        $parsed = $this->rubiesService->createRubiesPersonalAccount(
            (string) $validated['fname'],
            (string) $validated['lname'],
            (string) $validated['phone'],
            (string) $validated['dob'],
            strtolower(trim((string) $validated['email'])),
            $bvn !== '' ? $bvn : null,
            $nin !== '' ? $nin : null
        );

        return response()->json([
            'success' => true,
            'action' => 'createrubies_personal',
            'provider_endpoint' => '/V1/createrubies',
            'normalized' => $parsed,
        ]);
    }

    private function createRubiesBusiness(Request $request): JsonResponse
    {
        if (! $this->rubiesService->isConfigured()) {
            return response()->json([
                'success' => false,
                'action' => 'createrubies_business',
                'message' => 'Mevon Rubies is not configured (base_url/secret_key missing).',
            ], 422);
        }

        $validated = $request->validate([
            'cac' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|max:255',
        ]);

        $url = rtrim((string) (config('services.mevonrubies.base_url') ?: config('services.mevonpay.base_url', '')), '/')
            .'/'.ltrim((string) config('services.mevonrubies.create_path', '/V1/createrubies'), '/');
        $secret = trim((string) (config('services.mevonrubies.secret_key') ?: config('services.mevonpay.secret_key', '')));

        $payload = [
            'action' => 'create',
            'account_type' => 'business',
            'cac' => (string) $validated['cac'],
            'phone' => (string) $validated['phone'],
            'dob' => (string) $validated['dob'],
            'email' => strtolower(trim((string) $validated['email'])),
        ];

        $resp = Http::withHeaders([
            'Authorization' => $secret,
        ])
            ->acceptJson()
            ->asJson()
            ->timeout((int) (config('services.mevonrubies.timeout_seconds') ?: config('services.mevonpay.timeout_seconds', 20)))
            ->post($url, $payload);

        $json = $resp->json();

        return response()->json([
            'success' => $resp->successful(),
            'action' => 'createrubies_business',
            'provider_endpoint' => '/V1/createrubies',
            'http_status' => $resp->status(),
            'request' => $payload,
            'response' => is_array($json) ? $json : ['raw' => $resp->body()],
            'note' => 'Business Rubies creation is available for docs/testing. WhatsApp Tier 2 currently uses personal flow only.',
        ], $resp->successful() ? 200 : 502);
    }

    private function authorizeRequest(Request $request): void
    {
        $secret = trim((string) config('services.mevonpay.test_secret', ''));
        if ($secret === '') {
            return;
        }

        $provided = trim((string) $request->header('X-Test-Meveon-Secret', (string) $request->query('secret', '')));
        abort_unless(hash_equals($secret, $provided), 401, 'Unauthorized');
    }

    private function rubiesElectricityGetInfo(): JsonResponse
    {
        return $this->rubiesUtilityRequest(
            endpoint: '/V1/electricity',
            action: 'rubies_electricity_getInfo',
            payload: ['action' => 'getInfo']
        );
    }

    private function rubiesElectricityVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'meter' => 'required|string|max:40',
            'providerCode' => 'required|string|max:100',
            'planCode' => 'required|string|max:100',
        ]);

        return $this->rubiesUtilityRequest(
            endpoint: '/V1/electricity',
            action: 'rubies_electricity_verify',
            payload: [
                'action' => 'verify',
                'meter' => (string) $validated['meter'],
                'providerCode' => (string) $validated['providerCode'],
                'planCode' => (string) $validated['planCode'],
            ]
        );
    }

    private function rubiesElectricityBuy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'meter' => 'required|string|max:40',
            'providerCode' => 'required|string|max:100',
            'planCode' => 'required|string|max:100',
            'amount' => 'required|numeric|min:100',
            'customerName' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        return $this->rubiesUtilityRequest(
            endpoint: '/V1/electricity',
            action: 'rubies_electricity_buy',
            payload: [
                'action' => 'buy',
                'meter' => (string) $validated['meter'],
                'providerCode' => (string) $validated['providerCode'],
                'planCode' => (string) $validated['planCode'],
                'amount' => (float) $validated['amount'],
                'customerName' => (string) $validated['customerName'],
                'phone' => (string) $validated['phone'],
            ]
        );
    }

    private function rubiesCableGetInfo(): JsonResponse
    {
        return $this->rubiesUtilityRequest(
            endpoint: '/V1/cabletv',
            action: 'rubies_cable_getInfo',
            payload: ['action' => 'getInfo']
        );
    }

    private function rubiesCableVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smartcard' => 'required|string|max:40',
            'providerCode' => 'required|string|max:100',
            'planCode' => 'required|string|max:100',
        ]);

        return $this->rubiesUtilityRequest(
            endpoint: '/V1/cabletv',
            action: 'rubies_cable_verify',
            payload: [
                'action' => 'verify',
                'smartcard' => (string) $validated['smartcard'],
                'providerCode' => (string) $validated['providerCode'],
                'planCode' => (string) $validated['planCode'],
            ]
        );
    }

    private function rubiesCableBuy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smartcard' => 'required|string|max:40',
            'providerCode' => 'required|string|max:100',
            'planCode' => 'required|string|max:100',
            'amount' => 'required|numeric|min:100',
            'customerName' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        return $this->rubiesUtilityRequest(
            endpoint: '/V1/cabletv',
            action: 'rubies_cable_buy',
            payload: [
                'action' => 'buy',
                'smartcard' => (string) $validated['smartcard'],
                'providerCode' => (string) $validated['providerCode'],
                'planCode' => (string) $validated['planCode'],
                'amount' => (float) $validated['amount'],
                'customerName' => (string) $validated['customerName'],
                'phone' => (string) $validated['phone'],
            ]
        );
    }

    private function rubiesUtilityRequest(string $endpoint, string $action, array $payload): JsonResponse
    {
        $base = trim((string) config('services.mevonpay.base_url', ''));
        $secret = trim((string) config('services.mevonpay.secret_key', ''));

        if ($base === '' || $secret === '') {
            return response()->json([
                'success' => false,
                'action' => $action,
                'message' => 'MEVONPAY_BASE_URL or MEVONPAY_SECRET_KEY is not configured.',
            ], 422);
        }

        $url = rtrim($base, '/').'/'.ltrim($endpoint, '/');

        $resp = Http::withHeaders([
            'Authorization' => $secret,
        ])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.mevonpay.timeout_seconds', 20))
            ->post($url, $payload);

        $json = $resp->json();

        return response()->json([
            'success' => $resp->successful(),
            'action' => $action,
            'provider_endpoint' => $endpoint,
            'http_status' => $resp->status(),
            'request' => $payload,
            'response' => is_array($json) ? $json : ['raw' => $resp->body()],
        ], $resp->successful() ? 200 : 502);
    }
}

