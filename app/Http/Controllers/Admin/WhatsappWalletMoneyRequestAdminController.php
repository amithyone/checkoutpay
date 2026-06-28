<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWalletMoneyRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappWalletMoneyRequestAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = WhatsappWalletMoneyRequest::query()
            ->with([
                'requesterWallet:id,phone_e164,sender_name',
                'payerWallet:id,phone_e164,sender_name',
            ]);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', '')) ?? '';
        if ($phone !== '') {
            $query->where(function ($q) use ($phone) {
                $q->where('payer_phone_e164', 'like', '%'.$phone.'%')
                    ->orWhere('requester_phone_e164', 'like', '%'.$phone.'%');
            });
        }

        $requests = $query->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.whatsapp-wallet.money-requests.index', [
            'requests' => $requests,
            'pageTitle' => 'Money requests',
            'pageSubtitle' => 'Peer-to-peer payment requests between wallet users (read-only).',
            'statusFilter' => $status !== '' ? $status : 'all',
        ]);
    }
}
