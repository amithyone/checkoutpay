<?php

namespace App\Services\Consumer;

use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WalletConversationCapture;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappInboundHandler;
use App\Services\Whatsapp\WhatsappWaWalletMenuHandler;
use Illuminate\Support\Facades\Log;

/**
 * In-app wallet chat: same {@see WhatsappSession} state machine as WhatsApp; outbound replies are capture-only
 * (no Evolution) while {@see WalletConversationCapture} is active around each turn.
 *
 * Session sync: one row per `phone_e164`. WhatsApp webhooks and this API both advance the same session; the last
 * successful turn wins if both channels are used close together.
 */
final class ConsumerWalletConversationService
{
    public function __construct(
        private WhatsappInboundHandler $inbound,
        private WhatsappWaWalletMenuHandler $waWalletMenu,
    ) {}

    /**
     * @return array{messages: list<array{role: string, body: string}>, suggestions: list<array{label: string, insert_text: string}>, pin_required: array<string, mixed>|null}
     */
    public function turn(WhatsappWallet $wallet, string $text): array
    {
        $phone = (string) $wallet->phone_e164;
        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            $instance = (string) config('whatsapp.evolution.instance', '');
        }
        if ($instance === '') {
            Log::warning('consumer_wallet_conversation.missing_instance');

            return [
                'messages' => [['role' => 'assistant', 'body' => 'Chat is not configured on the server (missing WhatsApp instance).']],
                'suggestions' => [],
                'pin_required' => null,
            ];
        }

        $remoteJid = str_contains($phone, '@') ? $phone : $phone.'@s.whatsapp.net';
        $trim = trim($text);

        if ($trim === '') {
            WalletConversationCapture::start();
            try {
                $session = WhatsappSession::query()->firstOrNew(['phone_e164' => $phone]);
                $session->remote_jid = $remoteJid;
                $session->evolution_instance = $instance;
                $session->save();
                $linked = Renter::query()
                    ->where('whatsapp_phone_e164', $phone)
                    ->where('is_active', true)
                    ->first();
                $this->waWalletMenu->openMenu($session->fresh(), $instance, $phone, $linked?->fresh());
            } catch (\Throwable $e) {
                Log::error('consumer_wallet_conversation.open_menu', ['error' => $e->getMessage()]);
                WalletConversationCapture::append('Could not open the wallet menu. Try again or use WhatsApp.');
            } finally {
                $lines = WalletConversationCapture::drainAndStop();
            }
        } else {
            WalletConversationCapture::start();
            try {
                $this->inbound->handleConsumerAppTurn($phone, $trim);
            } catch (\Throwable $e) {
                Log::error('consumer_wallet_conversation.turn', ['error' => $e->getMessage(), 'phone' => $phone]);
                WalletConversationCapture::append('Something went wrong. Send MENU or try again shortly.');
            } finally {
                $lines = WalletConversationCapture::drainAndStop();
            }
        }

        $session = WhatsappSession::query()->where('phone_e164', $phone)->first();

        return [
            'messages' => array_map(
                static fn (string $body) => ['role' => 'assistant', 'body' => $body],
                $lines
            ),
            'suggestions' => $this->suggestionsFromLines($lines),
            'pin_required' => $this->detectPinRequired($session),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{label: string, insert_text: string}>
     */
    private function suggestionsFromLines(array $lines): array
    {
        $seen = [];
        $out = [];
        foreach ($lines as $line) {
            if (preg_match_all('/\*([^*]+)\*/', $line, $m)) {
                foreach ($m[1] as $inner) {
                    $label = trim((string) $inner);
                    if ($label === '') {
                        continue;
                    }
                    $insert = '*'.$label.'*';
                    if (isset($seen[$insert])) {
                        continue;
                    }
                    $seen[$insert] = true;
                    $out[] = ['label' => $insert, 'insert_text' => $insert];
                }
            }
        }

        return array_slice($out, 0, 24);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detectPinRequired(?WhatsappSession $session): ?array
    {
        if (! $session) {
            return null;
        }
        $session->refresh();
        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            return null;
        }
        $step = (string) ($ctx['step'] ?? '');
        if ($step === 'transfer_pin' || $step === 'p2p_pin') {
            $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
                ? trim($ctx['wallet_transfer_confirm_token'])
                : '';

            return [
                'kind' => 'wallet_transfer_web',
                'step' => $step,
                'confirm_token' => $token !== '' ? $token : null,
                'hint' => 'Confirm this debit with your 4-digit wallet PIN below (same as Transfer). This completes the pending send and clears the chat step without opening a browser link.',
            ];
        }
        if ($step !== '' && str_ends_with($step, '_pin_web')) {
            return [
                'kind' => 'vtu_web',
                'step' => $step,
                'confirm_token' => null,
                'hint' => 'Use Pay bills in the app with your wallet PIN to complete this purchase. If this chat still shows a PIN step afterward, send MENU.',
            ];
        }

        return null;
    }
}
