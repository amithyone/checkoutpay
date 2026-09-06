<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\PaymentLinkService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentLinkPaymentController extends Controller
{
    public const OPEN_AMOUNT_MIN = 100;

    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentLinkService $links
    ) {}

    public function show(Request $request, string $code): View|RedirectResponse
    {
        $link = PaymentLink::where('code', $code)->with('business')->firstOrFail();

        $link->increment('view_count');
        if (! $link->viewed_at) {
            $link->update(['viewed_at' => now()]);
        }

        if ($link->isPaid()) {
            return view('payment-links.paid', compact('link'));
        }

        if (in_array($link->status, [PaymentLink::STATUS_PAUSED, PaymentLink::STATUS_CANCELLED], true)) {
            return view('payment-links.closed', compact('link'));
        }

        $cardPaymentsEnabled = (bool) $link->business->card_payments_enabled;

        $selectedPayment = null;
        $paymentId = $request->query('payment_id');
        if ($paymentId) {
            $selectedPayment = $link->linkPayments()
                ->with('payment.accountNumberDetails')
                ->where('payment_id', $paymentId)
                ->first()?->payment;
        }

        if ($selectedPayment && $selectedPayment->isMevonCardCheckout() && $selectedPayment->isPending()) {
            $checkoutUrl = $selectedPayment->cardCheckoutPayload()['checkout_url'] ?? null;
            if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                return redirect()->away($checkoutUrl);
            }
        }

        if (! $selectedPayment && ! $cardPaymentsEnabled && $link->isOneTime() && ! $link->isOpenAmount()) {
            $selectedPayment = $this->existingPendingBank($link);
            if (! $selectedPayment) {
                $created = $this->createLinkedPayment($link, (float) $link->amount, $link->business->name.' customer');
                $selectedPayment = $created['payment'];
                $paymentSetupError = $created['error'];

                return view('payment-links.pay', [
                    'link' => $link,
                    'selectedPayment' => $selectedPayment,
                    'paymentSetupError' => $paymentSetupError,
                    'cardPaymentsEnabled' => $cardPaymentsEnabled,
                ]);
            }
        }

        return view('payment-links.pay', [
            'link' => $link,
            'selectedPayment' => $selectedPayment,
            'paymentSetupError' => null,
            'cardPaymentsEnabled' => $cardPaymentsEnabled,
        ]);
    }

    public function start(Request $request, string $code): RedirectResponse
    {
        $link = PaymentLink::where('code', $code)->with('business')->firstOrFail();

        if (! $link->canCollect()) {
            return redirect()->route('payment-links.pay', $code);
        }

        $cardPaymentsEnabled = (bool) $link->business->card_payments_enabled;
        $rules = [
            'payer_name' => 'required|string|max:255',
            'payment_method' => $cardPaymentsEnabled
                ? 'nullable|in:card,bank_transfer,bank'
                : 'nullable|in:bank_transfer,bank',
        ];
        if ($link->isOpenAmount()) {
            $rules['amount'] = 'required|numeric|min:'.self::OPEN_AMOUNT_MIN;
        }
        if ($cardPaymentsEnabled) {
            $rules['email'] = 'required_if:payment_method,card|nullable|email|max:255';
        }
        $validated = $request->validate($rules);

        $method = strtolower(trim((string) ($validated['payment_method'] ?? Payment::METHOD_BANK_TRANSFER)));
        if ($method === '' || $method === 'bank') {
            $method = Payment::METHOD_BANK_TRANSFER;
        }

        $amount = $link->isOpenAmount()
            ? (float) $validated['amount']
            : (float) $link->amount;

        if ($method === Payment::METHOD_BANK_TRANSFER && $link->isOneTime() && ! $link->isOpenAmount()) {
            $existing = $this->existingPendingBank($link);
            if ($existing) {
                return redirect()->route('payment-links.pay', [
                    'code' => $code,
                    'payment_id' => $existing->id,
                ]);
            }
        }

        $created = $this->createLinkedPayment(
            $link,
            $amount,
            $validated['payer_name'],
            $method,
            $validated['email'] ?? null
        );
        if ($created['error'] || ! $created['payment']) {
            return redirect()
                ->route('payment-links.pay', $code)
                ->withInput()
                ->with('error', $created['error'] ?? 'Could not start payment. Try again shortly.');
        }

        if ($created['payment']->isMevonCardCheckout()) {
            $checkoutUrl = $created['payment']->cardCheckoutPayload()['checkout_url'] ?? null;
            if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                return redirect()->away($checkoutUrl);
            }
        }

        return redirect()->route('payment-links.pay', [
            'code' => $code,
            'payment_id' => $created['payment']->id,
        ]);
    }

    public function webhook(Request $request, string $code)
    {
        $link = PaymentLink::where('code', $code)->firstOrFail();
        $paymentId = (int) ($request->input('payment_id') ?? 0);
        if ($paymentId > 0) {
            $payment = Payment::query()->find($paymentId);
            if ($payment && $payment->status === Payment::STATUS_APPROVED) {
                $this->links->recordApproved($link, $payment);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array{payment: ?Payment, error: ?string}
     */
    private function createLinkedPayment(
        PaymentLink $link,
        float $amount,
        string $payerName,
        string $method = Payment::METHOD_BANK_TRANSFER,
        ?string $email = null
    ): array {
        try {
            $payload = [
                'amount' => $amount,
                'payer_name' => $payerName,
                'webhook_url' => route('payment-links.payment.webhook', ['code' => $link->code]),
                'service' => 'payment_link',
                'business_website_id' => null,
                'currency' => $link->currency ?: 'NGN',
            ];
            if ($method === Payment::METHOD_CARD) {
                $payload['payment_method'] = Payment::METHOD_CARD;
                $payload['email'] = $email;
            }

            $payment = $this->paymentService->createPayment($payload, $link->business, request(), false);

            $emailData = $payment->email_data ?? [];
            $emailData['service'] = 'payment_link';
            $emailData['payment_link_id'] = $link->id;
            $payment->update(['email_data' => $emailData]);

            $this->links->attachPayment($link, $payment);
            $payment->load('accountNumberDetails');

            return ['payment' => $payment, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('payment_link.create_payment_failed', [
                'payment_link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'payment' => null,
                'error' => 'Payment could not be set up at the moment. Please try again in a few minutes.',
            ];
        }
    }

    private function existingPendingBank(PaymentLink $link): ?Payment
    {
        return $link->linkPayments()
            ->with('payment.accountNumberDetails')
            ->whereHas('payment', function ($q) {
                $q->where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('account_number')
                    ->where('account_number', '!=', '')
                    ->where(function ($exp) {
                        $exp->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->latest()
            ->first()?->payment;
    }
}
