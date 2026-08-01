<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\Support\SupportIssueOptionsService;
use App\Services\Support\WalletSupportStaffResolver;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappProactiveOutbound;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class WalletSupportTicketNotifier
{
    public function __construct(
        private WalletSupportStaffResolver $staffResolver,
        private ConsumerWalletPushNotificationService $push,
        private EvolutionWhatsAppClient $whatsapp,
    ) {}

    public function notifyStaffOfVisitorMessage(SupportTicket $ticket, string $messagePreview): void
    {
        if (! $this->isWalletQueueTicket($ticket)) {
            return;
        }

        $recipients = Admin::query()
            ->where('role', Admin::ROLE_WALLET_SUPPORT)
            ->where('is_active', true)
            ->where('handles_wallet_support_in_app', true)
            ->whereNotNull('whatsapp_e164')
            ->where('whatsapp_e164', '!=', '')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $preview = Str::limit(trim($messagePreview), 120);
        $ticketNumber = (string) $ticket->ticket_number;
        $title = 'Wallet support';
        $body = $preview !== ''
            ? "{$ticketNumber}: {$preview}"
            : "New message on {$ticketNumber}";

        foreach ($recipients as $admin) {
            $this->notifyAdmin($admin, $ticket, $title, $body);
        }
    }

    private function notifyAdmin(Admin $admin, SupportTicket $ticket, string $title, string $body): void
    {
        $phone = trim((string) $admin->whatsapp_e164);
        if ($phone === '') {
            return;
        }

        $account = ConsumerWalletApiAccount::query()
            ->where('phone_e164', $phone)
            ->orWhereHas('wallet', fn ($q) => $q->where('phone_e164', $phone))
            ->orderByDesc('last_app_active_at')
            ->first();

        $wallet = $account?->wallet;
        $pushed = false;

        if ($wallet !== null) {
            $this->push->notifyGeneric($wallet, $title, $body, [
                'type' => 'wallet_support_ticket',
                'screen' => 'support_staff',
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => (string) $ticket->ticket_number,
            ]);
            $pushed = true;
        }

        if ($pushed || ! WhatsappProactiveOutbound::enabled()) {
            return;
        }

        $instance = WhatsappEvolutionConfigResolver::walletInstanceForPhone($phone);
        if ($instance === '') {
            return;
        }

        try {
            $this->whatsapp->sendText(
                $instance,
                $phone,
                "*{$title}*\n\n{$body}\n\nOpen CheckoutNow → Support to reply."
            );
        } catch (\Throwable $e) {
            Log::warning('wallet_support_ticket_whatsapp_notify failed', [
                'admin_id' => $admin->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isWalletQueueTicket(SupportTicket $ticket): bool
    {
        if ($ticket->support_queue === SupportIssueOptionsService::QUEUE_WALLET) {
            return true;
        }

        return app(SupportIssueOptionsService::class)->isWalletQueue($ticket->issue_type);
    }
}
