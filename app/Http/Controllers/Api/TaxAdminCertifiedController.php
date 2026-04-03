<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NigtaxCertifiedReadyMail;
use App\Models\Business;
use App\Models\NigtaxCertifiedOrder;
use App\Models\NigtaxConsultant;
use App\Models\NigtaxConsultantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TaxAdminCertifiedController extends Controller
{
    /**
     * Global certified product settings + list of consultants (for admin UI).
     */
    public function certifiedSettings(): JsonResponse
    {
        $s = NigtaxConsultantSetting::singleton()->load('defaultConsultant');
        $consultants = NigtaxConsultant::query()->orderBy('id')->get();

        $paymentBusinessId = (int) config('services.nigtax.payment_business_id', 0);
        $paymentBusiness = $paymentBusinessId > 0 ? Business::query()->find($paymentBusinessId) : null;
        $hasWebsite = $paymentBusiness && (
            $paymentBusiness->approvedWebsites()->exists()
            || $paymentBusiness->websites()->exists()
        );
        $paymentReady = $paymentBusiness !== null && $hasWebsite;

        return response()->json([
            'certified_fee_ngn' => (float) $s->certified_fee_ngn,
            'is_enabled' => (bool) $s->is_enabled,
            'signatures_applied_count' => (int) $s->signatures_applied_count,
            'default_consultant_id' => $s->default_consultant_id,
            'consultants' => $consultants->map(fn (NigtaxConsultant $c) => $this->consultantPayload($c))->all(),
            'payment_business_id' => $paymentBusinessId > 0 ? $paymentBusinessId : null,
            'payment_ready' => $paymentReady,
            'payment_setup_hint' => $paymentReady ? null : (
                $paymentBusinessId < 1
                    ? 'Set NIGTAX_PAYMENT_BUSINESS_ID in the checkout app .env to the numeric id of a Business (see businesses table or Checkout admin), then run php artisan config:clear.'
                    : ($paymentBusiness === null
                        ? 'NIGTAX_PAYMENT_BUSINESS_ID does not match any Business. Fix the id in .env and run php artisan config:clear.'
                        : 'This business has no website record. Add an approved website for that business in Checkout so virtual accounts and webhooks work.')
            ),
        ]);
    }

    public function certifiedSettingsUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'certified_fee_ngn' => 'sometimes|numeric|min:0',
            'is_enabled' => 'sometimes|boolean',
            'default_consultant_id' => 'nullable|exists:nigtax_consultants,id',
        ]);

        $s = NigtaxConsultantSetting::singleton();
        $s->fill(collect($validated)->only(['certified_fee_ngn', 'is_enabled', 'default_consultant_id'])->all());
        $s->save();

        return $this->certifiedSettings();
    }

    public function consultantsIndex(): JsonResponse
    {
        $rows = NigtaxConsultant::query()->orderBy('id')->get();

        return response()->json([
            'data' => $rows->map(fn (NigtaxConsultant $c) => $this->consultantPayload($c))->all(),
        ]);
    }

    public function consultantsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consultant_name' => 'nullable|string|max:255',
            'firm_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'license_number' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email',
            'is_active' => 'sometimes|boolean',
        ]);

        $c = NigtaxConsultant::create($validated);

        $s = NigtaxConsultantSetting::singleton();
        if ($s->default_consultant_id === null) {
            $s->default_consultant_id = $c->id;
            $s->save();
        }

        return response()->json($this->consultantPayload($c->fresh()), 201);
    }

    public function consultantsUpdate(Request $request, int $id): JsonResponse
    {
        $c = NigtaxConsultant::query()->findOrFail($id);
        $validated = $request->validate([
            'consultant_name' => 'nullable|string|max:255',
            'firm_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'license_number' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email',
            'is_active' => 'sometimes|boolean',
        ]);
        $c->fill($validated);
        $c->save();

        return response()->json($this->consultantPayload($c->fresh()));
    }

    public function consultantsDestroy(int $id): JsonResponse
    {
        $c = NigtaxConsultant::query()->findOrFail($id);
        $s = NigtaxConsultantSetting::singleton();
        if ($s->default_consultant_id === $c->id) {
            $next = NigtaxConsultant::query()->where('id', '!=', $c->id)->orderBy('id')->first();
            $s->default_consultant_id = $next?->id;
            $s->save();
        }
        if ($c->signature_image_path && Storage::disk('public')->exists($c->signature_image_path)) {
            Storage::disk('public')->delete($c->signature_image_path);
        }
        if ($c->stamp_image_path && Storage::disk('public')->exists($c->stamp_image_path)) {
            Storage::disk('public')->delete($c->stamp_image_path);
        }
        $c->delete();

        return response()->json(['success' => true]);
    }

    public function uploadConsultantSignature(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:4096',
        ]);
        $c = NigtaxConsultant::query()->findOrFail($id);
        $path = $request->file('file')->store('nigtax/consultants/'.$c->id, 'public');
        if ($c->signature_image_path && Storage::disk('public')->exists($c->signature_image_path)) {
            Storage::disk('public')->delete($c->signature_image_path);
        }
        $c->update(['signature_image_path' => $path]);

        return response()->json($this->consultantPayload($c->fresh()));
    }

    public function uploadConsultantStamp(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:4096',
        ]);
        $c = NigtaxConsultant::query()->findOrFail($id);
        $path = $request->file('file')->store('nigtax/consultants/'.$c->id, 'public');
        if ($c->stamp_image_path && Storage::disk('public')->exists($c->stamp_image_path)) {
            Storage::disk('public')->delete($c->stamp_image_path);
        }
        $c->update(['stamp_image_path' => $path]);

        return response()->json($this->consultantPayload($c->fresh()));
    }

    /** @deprecated Use certifiedSettings — kept for older admin bundles */
    public function consultantShow(): JsonResponse
    {
        return $this->certifiedSettings();
    }

    /** @deprecated Use PUT /certified/consultants/{id} — updates the default consultant profile */
    public function consultantUpdate(Request $request): JsonResponse
    {
        $s = NigtaxConsultantSetting::singleton();
        if (!$s->default_consultant_id) {
            return response()->json(['message' => 'Set a default consultant first.'], 422);
        }

        return $this->consultantsUpdate($request, (int) $s->default_consultant_id);
    }

    /** @deprecated Use uploadConsultantSignature */
    public function uploadSignature(Request $request): JsonResponse
    {
        $s = NigtaxConsultantSetting::singleton();
        $id = $s->default_consultant_id;
        if (!$id) {
            return response()->json(['message' => 'No default consultant set.'], 422);
        }

        return $this->uploadConsultantSignature($request, $id);
    }

    /** @deprecated Use uploadConsultantStamp */
    public function uploadStamp(Request $request): JsonResponse
    {
        $s = NigtaxConsultantSetting::singleton();
        $id = $s->default_consultant_id;
        if (!$id) {
            return response()->json(['message' => 'No default consultant set.'], 422);
        }

        return $this->uploadConsultantStamp($request, $id);
    }

    public function ordersIndex(Request $request): JsonResponse
    {
        $q = NigtaxCertifiedOrder::query()->with(['payment', 'consultant']);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        $orders = $q->orderByDesc('id')->paginate(25)->through(fn (NigtaxCertifiedOrder $o) => $this->orderSummary($o));

        return response()->json($orders);
    }

    public function orderShow(int $id): JsonResponse
    {
        $order = NigtaxCertifiedOrder::with(['payment.accountNumberDetails', 'consultant'])->findOrFail($id);

        return response()->json($this->orderDetail($order));
    }

    public function orderUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:'.implode(',', [
                NigtaxCertifiedOrder::STATUS_SIGNED,
                NigtaxCertifiedOrder::STATUS_DELIVERED,
                NigtaxCertifiedOrder::STATUS_CANCELLED,
            ]),
            'admin_notes' => 'nullable|string',
            'signed_pdf' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        try {
            $mailOrder = DB::transaction(function () use ($request, $validated, $id) {
                /** @var NigtaxCertifiedOrder $order */
                $order = NigtaxCertifiedOrder::query()->lockForUpdate()->findOrFail($id);

                if (isset($validated['admin_notes'])) {
                    $order->admin_notes = $validated['admin_notes'];
                }

                if ($request->hasFile('signed_pdf')) {
                    $path = $request->file('signed_pdf')->store('nigtax/certified/'.$order->id, 'public');
                    if ($order->signed_pdf_path && Storage::disk('public')->exists($order->signed_pdf_path)) {
                        Storage::disk('public')->delete($order->signed_pdf_path);
                    }
                    $order->signed_pdf_path = $path;
                }

                $newStatus = $validated['status'] ?? null;
                if ($newStatus !== null) {
                    $this->applyOrderStatusChange($order, $newStatus);
                }

                $order->save();

                return $newStatus === NigtaxCertifiedOrder::STATUS_DELIVERED ? $order->fresh() : null;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($mailOrder) {
            try {
                Mail::to($mailOrder->customer_email)->send(new NigtaxCertifiedReadyMail($mailOrder));
            } catch (\Throwable $e) {
                Log::warning('NigTax certified ready email failed', [
                    'order_id' => $mailOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $order = NigtaxCertifiedOrder::with(['payment.accountNumberDetails', 'consultant'])->findOrFail($id);

        return response()->json($this->orderDetail($order));
    }

    protected function applyOrderStatusChange(NigtaxCertifiedOrder $order, string $newStatus): void
    {
        if ($newStatus === NigtaxCertifiedOrder::STATUS_CANCELLED) {
            if ($order->status !== NigtaxCertifiedOrder::STATUS_AWAITING_PAYMENT) {
                throw new \RuntimeException('Only orders awaiting payment can be cancelled.');
            }
            $order->status = NigtaxCertifiedOrder::STATUS_CANCELLED;

            return;
        }

        if ($newStatus === NigtaxCertifiedOrder::STATUS_SIGNED) {
            if ($order->status !== NigtaxCertifiedOrder::STATUS_PAID) {
                throw new \RuntimeException('Order must be paid before marking as signed.');
            }
            $order->status = NigtaxCertifiedOrder::STATUS_SIGNED;
            $order->signed_at = now();
            NigtaxConsultantSetting::singleton()->increment('signatures_applied_count');

            return;
        }

        if ($newStatus === NigtaxCertifiedOrder::STATUS_DELIVERED) {
            if (!$order->signed_pdf_path) {
                throw new \RuntimeException('Upload the signed PDF before marking as delivered.');
            }
            if (!in_array($order->status, [NigtaxCertifiedOrder::STATUS_PAID, NigtaxCertifiedOrder::STATUS_SIGNED], true)) {
                throw new \RuntimeException('Order must be paid (or signed) before delivery.');
            }

            if ($order->status === NigtaxCertifiedOrder::STATUS_PAID) {
                $order->signed_at = now();
                NigtaxConsultantSetting::singleton()->increment('signatures_applied_count');
            }

            $order->status = NigtaxCertifiedOrder::STATUS_DELIVERED;
            $order->delivered_at = now();
        }
    }

    protected function consultantPayload(NigtaxConsultant $c): array
    {
        $disk = Storage::disk('public');

        $url = static function (?string $path) use ($disk): ?string {
            if (!$path || !$disk->exists($path)) {
                return null;
            }

            return url(Storage::url($path));
        };

        return [
            'id' => $c->id,
            'consultant_name' => $c->consultant_name,
            'firm_name' => $c->firm_name,
            'title' => $c->title,
            'bio' => $c->bio,
            'license_number' => $c->license_number,
            'contact_email' => $c->contact_email,
            'is_active' => (bool) $c->is_active,
            'signature_url' => $url($c->signature_image_path),
            'stamp_url' => $url($c->stamp_image_path),
        ];
    }

    protected function orderSummary(NigtaxCertifiedOrder $order): array
    {
        return [
            'id' => $order->id,
            'consultant_id' => $order->consultant_id,
            'consultant_name' => $order->consultant?->consultant_name,
            'customer_email' => $order->customer_email,
            'customer_name' => $order->customer_name,
            'report_type' => $order->report_type,
            'amount_paid' => (float) $order->amount_paid,
            'status' => $order->status,
            'transaction_id' => $order->transaction_id,
            'payment_status' => $order->payment?->status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'signed_at' => $order->signed_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    protected function orderDetail(NigtaxCertifiedOrder $order): array
    {
        $disk = Storage::disk('public');
        $pdfUrl = null;
        if ($order->signed_pdf_path && $disk->exists($order->signed_pdf_path)) {
            $pdfUrl = url(Storage::url($order->signed_pdf_path));
        }

        $summary = $this->orderSummary($order);
        $summary['admin_notes'] = $order->admin_notes;
        $summary['signed_pdf_url'] = $pdfUrl;
        $summary['report_snapshot'] = $order->report_snapshot_json
            ? json_decode($order->report_snapshot_json, true)
            : null;

        $payment = $order->payment;
        if ($payment) {
            $summary['payment'] = [
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'account_number' => $payment->account_number,
                'account_name' => $payment->accountNumberDetails->account_name ?? null,
                'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
            ];
        }

        return $summary;
    }
}
