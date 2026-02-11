<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Business;
use App\Mail\InvoiceSent;
use App\Mail\InvoicePaymentReceipt;
use App\Services\ChargeService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoiceService
{
    /**
     * Create a new invoice
     */
    public function createInvoice(array $data, Business $business): Invoice
    {
        // Handle logo upload
        $logoPath = null;
        if (isset($data['logo']) && $data['logo']->isValid()) {
            $logoPath = $data['logo']->store('invoices/logos', 'public');
        }

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'logo' => $logoPath,
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'client_phone' => $data['client_phone'] ?? null,
            'client_address' => $data['client_address'] ?? null,
            'client_company' => $data['client_company'] ?? null,
            'client_tax_id' => $data['client_tax_id'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? now(),
            'due_date' => $data['due_date'] ?? null,
            'currency' => $data['currency'] ?? 'NGN',
            'tax_rate' => $data['tax_rate'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'discount_type' => $data['discount_type'] ?? null,
            'notes' => $data['notes'] ?? null,
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'allow_split_payment' => !empty($data['allow_split_payment']),
            'split_installments' => $data['split_installments'] ?? null,
            'split_percentages' => isset($data['split_percentages']) && is_array($data['split_percentages'])
                ? array_values(array_map('floatval', $data['split_percentages']))
                : null,
        ]);

        // Add invoice items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'sort_order' => $index,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? 'unit',
                    'unit_price' => $item['unit_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        }

        // Calculate totals
        $invoice->calculateTotals();
        $invoice->refresh();

        return $invoice;
    }

    /**
     * Generate QR code for invoice payment link
     * Uses SVG format which doesn't require imagick extension
     */
    public function generateQrCode(Invoice $invoice): string
    {
        $paymentUrl = $invoice->payment_link_url;
        
        // Use SVG format (doesn't require imagick extension)
        // SVG works well in PDFs and emails and scales better
        $qrCode = QrCode::format('svg')
            ->size(200)
            ->margin(2)
            ->generate($paymentUrl);
        
        // SVG is already a string, return as data URI
        return 'data:image/svg+xml;base64,' . base64_encode($qrCode);
    }

    /**
     * Send invoice via email to both sender and receiver
     */
    public function sendInvoice(Invoice $invoice, $attachPdf = true): bool
    {
        try {
            $invoice->load(['business', 'items']);

            // Generate QR code for email
            $qrCodeBase64 = $this->generateQrCode($invoice);

            // Send to receiver (client)
            $receiverMail = new InvoiceSent($invoice, false, $qrCodeBase64);
            if ($attachPdf) {
                $pdfService = app(InvoicePdfService::class);
                $pdfPath = $pdfService->generatePdf($invoice, $qrCodeBase64);
                if ($pdfPath && file_exists($pdfPath)) {
                    $receiverMail->attach($pdfPath, [
                        'as' => "Invoice-{$invoice->invoice_number}.pdf",
                        'mime' => 'application/pdf',
                    ]);
                }
            }
            Mail::to($invoice->client_email)->send($receiverMail);

            // Send to sender (business)
            if ($invoice->business->email && $invoice->business->shouldReceiveEmailNotifications()) {
                $senderMail = new InvoiceSent($invoice, true, $qrCodeBase64);
                if ($attachPdf) {
                    $pdfService = app(InvoicePdfService::class);
                    $pdfPath = $pdfService->generatePdf($invoice, $qrCodeBase64);
                    if ($pdfPath && file_exists($pdfPath)) {
                        $senderMail->attach($pdfPath, [
                            'as' => "Invoice-{$invoice->invoice_number}.pdf",
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
                Mail::to($invoice->business->email)->send($senderMail);
            }

            // Update invoice status
            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
                'email_sent_to_sender' => true,
                'email_sent_to_receiver' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invoice emails', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Record a split payment approval: credit business for this slice, update paid_amount, mark fully paid when total reached.
     */
    public function recordSplitPaymentApproved(Invoice $invoice, \App\Models\Payment $payment, float $sliceAmount): bool
    {
        $invoice->load(['business', 'invoicePayments.payment']);

        $chargeService = app(ChargeService::class);
        $charges = $chargeService->calculateInvoiceCharges($sliceAmount, $invoice->business);
        $businessReceives = $charges['business_receives'] ?? $sliceAmount;

        $invoice->business->increment('balance', $businessReceives);

        $totalPaid = (float) $invoice->invoicePayments()
            ->whereHas('payment', fn ($q) => $q->where('status', \App\Models\Payment::STATUS_APPROVED))
            ->sum('amount');

        $invoice->update([
            'paid_amount' => $totalPaid,
            'payment_id' => $invoice->payment_id ?? $payment->id,
        ]);

        // Send receipt to both parties for this payment (amount, due date, next payment reminder)
        $remainingAfter = max(0, (float) $invoice->total_amount - $totalPaid);
        $nextAmount = null;
        if ($remainingAfter >= 0.01 && $invoice->allow_split_payment && !empty($invoice->split_percentages)) {
            $suggested = $invoice->getSuggestedSplitAmounts();
            foreach ($suggested as $s) {
                $a = (float) $s['amount'];
                if ($a >= 0.01 && $a <= $remainingAfter) {
                    $nextAmount = round($a, 2);
                    break;
                }
            }
        }
        $this->sendPaymentReceipt($invoice, $payment, $sliceAmount, $remainingAfter, $nextAmount);

        if ($totalPaid >= (float) $invoice->total_amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            $this->sendPaymentConfirmation($invoice);
        }

        return true;
    }

    /**
     * Mark invoice as paid and send notifications
     */
    public function markAsPaid(Invoice $invoice, $paymentId = null, $paidAmount = null): bool
    {
        try {
            $invoice->load(['business', 'items']);

            $paidAmount = $paidAmount ?? $invoice->total_amount;

            // Calculate invoice charges if applicable
            $chargeService = app(ChargeService::class);
            $charges = $chargeService->calculateInvoiceCharges($paidAmount, $invoice->business);

            $updateData = [
                'status' => 'paid',
                'paid_at' => now(),
                'paid_amount' => $paidAmount,
            ];

            if ($paymentId) {
                $updateData['payment_id'] = $paymentId;
            }

            $invoice->update($updateData);

            // Update business balance with charges if applicable
            if ($invoice->business_id && $charges['total_charges'] > 0) {
                // Business receives the amount after charges
                $businessReceives = $charges['business_receives'];
                $invoice->business->increment('balance', $businessReceives);
                
                Log::info('Invoice charges applied', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'paid_amount' => $paidAmount,
                    'charges' => $charges['total_charges'],
                    'business_receives' => $businessReceives,
                ]);
            } else {
                // No charges, business receives full amount
                $invoice->business->increment('balance', $paidAmount);
            }

            // Send payment confirmation emails
            $this->sendPaymentConfirmation($invoice);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark invoice as paid', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send payment receipt to both parties (for each payment, e.g. split). Includes amount, due date, and next payment reminder.
     */
    public function sendPaymentReceipt(
        Invoice $invoice,
        \App\Models\Payment $payment,
        float $amount,
        float $remaining = 0,
        ?float $nextPaymentAmount = null
    ): bool {
        try {
            $invoice->load(['business']);

            Mail::to($invoice->client_email)->send(new InvoicePaymentReceipt(
                $invoice,
                $payment,
                $amount,
                false,
                $remaining,
                $nextPaymentAmount
            ));

            if ($invoice->business->email && $invoice->business->shouldReceivePaymentNotifications()) {
                Mail::to($invoice->business->email)->send(new InvoicePaymentReceipt(
                    $invoice,
                    $payment,
                    $amount,
                    true,
                    $remaining,
                    $nextPaymentAmount
                ));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment receipt emails', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send payment confirmation emails to both parties with updated PDF
     */
    public function sendPaymentConfirmation(Invoice $invoice): bool
    {
        try {
            $invoice->load(['business', 'items']);

            // Regenerate PDF with PAID status
            $pdfService = app(InvoicePdfService::class);
            $pdfPath = $pdfService->generatePdf($invoice, null, true); // Pass true for paid status

            // Send to receiver (client) with updated PDF
            $receiverMail = new \App\Mail\InvoicePaid($invoice, false);
            if ($pdfPath && file_exists($pdfPath)) {
                $receiverMail->attach($pdfPath, [
                    'as' => "Invoice-{$invoice->invoice_number}-PAID.pdf",
                    'mime' => 'application/pdf',
                ]);
            }
            Mail::to($invoice->client_email)->send($receiverMail);

            // Send to sender (business) with updated PDF
            if ($invoice->business->email && $invoice->business->shouldReceivePaymentNotifications()) {
                $senderMail = new \App\Mail\InvoicePaid($invoice, true);
                if ($pdfPath && file_exists($pdfPath)) {
                    $senderMail->attach($pdfPath, [
                        'as' => "Invoice-{$invoice->invoice_number}-PAID.pdf",
                        'mime' => 'application/pdf',
                    ]);
                }
                Mail::to($invoice->business->email)->send($senderMail);
            }

            // Update flags
            $invoice->update([
                'payment_email_sent_to_sender' => true,
                'payment_email_sent_to_receiver' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation emails', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update invoice
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        // Handle logo upload
        if (isset($data['logo']) && $data['logo']->isValid()) {
            // Delete old logo if exists
            if ($invoice->logo && Storage::disk('public')->exists($invoice->logo)) {
                Storage::disk('public')->delete($invoice->logo);
            }
            // Store new logo
            $logoPath = $data['logo']->store('invoices/logos', 'public');
            $data['logo'] = $logoPath;
        } else {
            // Keep existing logo if no new logo uploaded
            unset($data['logo']);
        }

        $invoice->update([
            'allow_split_payment' => !empty($data['allow_split_payment']),
            'split_installments' => $data['split_installments'] ?? null,
            'split_percentages' => isset($data['split_percentages']) && is_array($data['split_percentages'])
                ? array_values(array_map('floatval', $data['split_percentages']))
                : null,
            'client_name' => $data['client_name'] ?? $invoice->client_name,
            'client_email' => $data['client_email'] ?? $invoice->client_email,
            'client_phone' => $data['client_phone'] ?? $invoice->client_phone,
            'client_address' => $data['client_address'] ?? $invoice->client_address,
            'client_company' => $data['client_company'] ?? $invoice->client_company,
            'client_tax_id' => $data['client_tax_id'] ?? $invoice->client_tax_id,
            'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            'due_date' => $data['due_date'] ?? $invoice->due_date,
            'currency' => $data['currency'] ?? $invoice->currency,
            'tax_rate' => $data['tax_rate'] ?? $invoice->tax_rate,
            'discount_amount' => $data['discount_amount'] ?? $invoice->discount_amount,
            'discount_type' => $data['discount_type'] ?? $invoice->discount_type,
            'notes' => $data['notes'] ?? $invoice->notes,
            'terms_and_conditions' => $data['terms_and_conditions'] ?? $invoice->terms_and_conditions,
            'reference_number' => $data['reference_number'] ?? $invoice->reference_number,
        ]);

        // Update logo if provided
        if (isset($data['logo'])) {
            $invoice->update(['logo' => $data['logo']]);
        }

        // Update items if provided
        if (isset($data['items']) && is_array($data['items'])) {
            // Delete existing items
            $invoice->items()->delete();

            // Create new items
            foreach ($data['items'] as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'sort_order' => $index,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? 'unit',
                    'unit_price' => $item['unit_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        }

        // Recalculate totals
        $invoice->calculateTotals();
        $invoice->refresh();

        return $invoice;
    }
}
