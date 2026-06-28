<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletSaveTogetherPot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappSaveTogetherAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = WalletSaveTogetherPot::query()
            ->with([
                'creatorWallet:id,phone_e164,sender_name',
                'members',
            ]);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', '')) ?? '';
        if ($phone !== '') {
            $query->whereHas('members', function ($q) use ($phone) {
                $q->where('phone_e164', 'like', '%'.$phone.'%');
            });
        }

        $pots = $query->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.whatsapp-wallet.save-together.index', [
            'pots' => $pots,
            'pageTitle' => 'Save Together',
            'pageSubtitle' => 'Group savings pots with escrowed contributions (read-only).',
            'statusFilter' => $status !== '' ? $status : 'all',
        ]);
    }
}
