<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Business;
use App\Mail\InvoiceSent;
use App\Services\ChargeService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
     * Send invoice via email to both sender and receiver
     */
    public function sendInvoice(Invoice $invoice, $attachPdf = true): bool
    {
        try {
            $invoice->load(['business', 'items']);

            // Send to receiver (client)
            $receiverMail = new InvoiceSent($invoice, false);
            if ($attachPdf) {
                $pdfService = app(InvoicePdfService::class);
                $pdfPath = $pdfService->generatePdf($invoice);
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
                $senderMail = new InvoiceSent($invoice, true);
                if ($attachPdf) {
                    $pdfService = app(InvoicePdfService::class);
                    $pdfPath = $pdfService->generatePdf($invoice);
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
     * Send payment confirmation emails to both parties
     */
    public function sendPaymentConfirmation(Invoice $invoice): bool
    {
        try {
            $invoice->load(['business', 'items']);

            // Send to receiver (client)
            Mail::to($invoice->client_email)->send(new \App\Mail\InvoicePaid($invoice, false));

            // Send to sender (business)
            if ($invoice->business->email && $invoice->business->shouldReceivePaymentNotifications()) {
                Mail::to($invoice->business->email)->send(new \App\Mail\InvoicePaid($invoice, true));
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
