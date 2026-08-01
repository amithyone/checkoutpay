<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Services\Support\WalletSupportStaffResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerSupportContextController extends Controller
{
    public function __construct(
        private WalletSupportStaffResolver $staffResolver,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var ConsumerWalletApiAccount $account */
        $account = $request->user();
        $admin = $this->staffResolver->resolveForAccount($account);

        if ($admin === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'mode' => 'customer',
                    'poll_interval_seconds' => (int) config('support.poll_interval_seconds', 5),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'staff',
                'staff' => [
                    'admin_id' => $admin->id,
                    'name' => $admin->name,
                    'unread_total' => $this->staffResolver->unreadWalletQueueTotal(),
                ],
                'poll_interval_seconds' => (int) config('support.poll_interval_seconds', 5),
            ],
        ]);
    }
}
