<?php

namespace App\Http\Controllers;

use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\WhatsappInboundHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsappMagicLinkController extends Controller
{
    public function confirm(Request $request, WhatsappInboundHandler $handler): RedirectResponse
    {
        $token = $request->query('t', '');
        if (! is_string($token) || strlen($token) < 32) {
            return redirect()->route('home')->with('error', 'Invalid link.');
        }

        $hash = hash('sha256', $token);
        $session = WhatsappSession::query()
            ->where('magic_link_token_hash', $hash)
            ->where('magic_link_expires_at', '>', now())
            ->first();

        if (! $session || $session->state !== WhatsappSession::STATE_AWAIT_OTP) {
            return redirect()->route('home')->with('error', 'This link has expired or was already used.');
        }

        $email = $session->pending_email;
        if ($email === null || $email === '') {
            return redirect()->route('home')->with('error', 'Invalid session.');
        }

        $renter = Renter::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $renter) {
            return redirect()->route('home')->with('error', 'Account not found.');
        }

        $phone = $session->phone_e164;
        $existing = Renter::query()
            ->where('whatsapp_phone_e164', $phone)
            ->where('id', '!=', $renter->id)
            ->exists();

        if ($existing) {
            return redirect()->route('home')->with('error', 'This WhatsApp number is already linked to another account.');
        }

        $handler->completeLinkAfterVerification($session, $renter);

        return redirect()->route('home')->with('success', 'WhatsApp linked. Check your chat for a confirmation.');
    }
}
