<?php

namespace App\Services;

use App\Models\Business;
use App\Models\NigtaxCertifiedOrder;
use App\Models\NigtaxConsultant;
use App\Models\NigtaxConsultantSetting;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NigtaxCertifiedReportService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function resolvePrimaryConsultant(): ?NigtaxConsultant
    {
        $settings = NigtaxConsultantSetting::singleton();
        if ($settings->default_consultant_id) {
            $c = NigtaxConsultant::query()
                ->where('id', $settings->default_consultant_id)
                ->where('is_active', true)
                ->first();
            if ($c) {
                return $c;
            }
        }

        return NigtaxConsultant::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function getSettingsForPublic(): array
    {
        $s = NigtaxConsultantSetting::singleton();
        $c = $this->resolvePrimaryConsultant();
        $disk = Storage::disk('public');

        $url = static function (?string $path) use ($disk): ?string {
            if (!$path || !$disk->exists($path)) {
                return null;
            }

            return url(Storage::url($path));
        };

        $isAvailable = (bool) $s->is_enabled
            && (float) $s->certified_fee_ngn >= 0.01
            && $c !== null;

        return [
            'is_enabled' => $isAvailable,
            'fee_ngn' => (float) $s->certified_fee_ngn,
            'consultant_name' => $c?->consultant_name,
            'firm_name' => $c?->firm_name,
            'title' => $c?->title,
            'license_number' => $c?->license_number,
            'bio' => $c?->bio,
            'signature_url' => $c ? $url($c->signature_image_path) : null,
            'stamp_url' => $c ? $url($c->stamp_image_path) : null,
        ];
    }

    /**
     * @param  array{customer_email: string, customer_name?: string, report_type: string, report_snapshot: array|null}  $data
     * @return array{order: NigtaxCertifiedOrder, payment: Payment}
     */
    public function createOrderWithPayment(array $data): array
    {
        $settings = NigtaxConsultantSetting::singleton();
        $consultant = $this->resolvePrimaryConsultant();
        if (!$settings->is_enabled || (float) $settings->certified_fee_ngn < 0.01 || !$consultant) {
            throw new \RuntimeException('Certified reports are not available.');
        }

        $businessId = (int) config('services.nigtax.payment_business_id', 0);
        if ($businessId < 1) {
            throw new \RuntimeException(
                'NigTax payment business is not configured. On the checkout server, set NIGTAX_PAYMENT_BUSINESS_ID in .env to your Business id (from the businesses table), then run: php artisan config:clear'
            );
        }

        $business = Business::query()->findOrFail($businessId);
        $website = $business->approvedWebsites()->first() ?? $business->websites()->first();
        if (!$website) {
            throw new \RuntimeException('Payment business has no website configured for webhooks.');
        }

        $fallbackWebhook = config('services.nigtax.payment_webhook_url');
        if (!is_string($fallbackWebhook) || $fallbackWebhook === '') {
            $fallbackWebhook = rtrim((string) $website->website_url, '/');
        }
        // Align with PaymentService / approved website row so webhook mismatch guard does not block VA assignment.
        $webhookUrl = filled($website->webhook_url)
            ? (string) $website->webhook_url
            : $fallbackWebhook;

        $amount = (float) $settings->certified_fee_ngn;

        $order = NigtaxCertifiedOrder::create([
            'consultant_id' => $consultant->id,
            'customer_email' => $data['customer_email'],
            'customer_name' => $data['customer_name'] ?? null,
            'report_type' => $data['report_type'],
            'report_snapshot_json' => isset($data['report_snapshot'])
                ? json_encode($data['report_snapshot'], JSON_THROW_ON_ERROR)
                : null,
            'amount_paid' => $amount,
            'status' => NigtaxCertifiedOrder::STATUS_AWAITING_PAYMENT,
        ]);

        $paymentData = [
            'amount' => $amount,
            'payer_name' => $data['customer_name'] ?: $data['customer_email'],
            'webhook_url' => $webhookUrl,
            'service' => 'nigtax_certified',
            'business_website_id' => $website->id,
            'website_url' => $website->website_url,
        ];

        try {
            $payment = $this->paymentService->createPayment($paymentData, $business, null);
        } catch (\Throwable $e) {
            $order->delete();
            throw $e;
        }

        $emailData = $payment->email_data ?? [];
        $emailData['nigtax_certified_order_id'] = $order->id;
        $emailData['service'] = 'nigtax_certified';
        $payment->update([
            'email_data' => $emailData,
        ]);

        $order->update([
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
        ]);

        return ['order' => $order->fresh(), 'payment' => $payment->fresh(['accountNumberDetails'])];
    }

    public function markPaidFromPayment(Payment $payment): void
    {
        $emailData = $payment->email_data ?? [];
        $orderId = $emailData['nigtax_certified_order_id'] ?? null;
        if (!$orderId) {
            return;
        }

        $order = NigtaxCertifiedOrder::query()->find($orderId);
        if (!$order || $order->status !== NigtaxCertifiedOrder::STATUS_AWAITING_PAYMENT) {
            return;
        }

        $order->update([
            'status' => NigtaxCertifiedOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(
                new \App\Mail\NigtaxCertifiedPaidMail($order)
            );
        } catch (\Throwable $e) {
            Log::warning('NigTax certified paid email failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
