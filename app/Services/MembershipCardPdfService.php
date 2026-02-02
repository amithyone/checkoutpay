<?php

namespace App\Services;

use App\Models\MembershipSubscription;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MembershipCardPdfService
{
    /**
     * Generate QR code for membership subscription
     */
    public function generateQrCode(MembershipSubscription $subscription): string
    {
        // Ensure relationships are loaded
        if (!$subscription->relationLoaded('membership')) {
            $subscription->load('membership.business');
        }

        // QR code data: subscription number, member name, expiry date
        $qrData = json_encode([
            'subscription_number' => $subscription->subscription_number,
            'member_name' => $subscription->member_name,
            'member_email' => $subscription->member_email,
            'membership' => $subscription->membership->name,
            'expires_at' => $subscription->expires_at->format('Y-m-d'),
            'business' => $subscription->membership->business->name,
        ]);

        // Generate QR code as base64 image
        $qrCode = QrCode::format('png')
            ->size(200)
            ->generate($qrData);

        return base64_encode($qrCode);
    }

    /**
     * Generate PDF membership card
     */
    public function generatePdf(MembershipSubscription $subscription): ?string
    {
        try {
            $subscription->load(['membership.business', 'membership.category']);

            // Generate QR code
            $qrCodeBase64 = $this->generateQrCode($subscription);
            $subscription->qr_code_data = $qrCodeBase64;
            $subscription->save();

            // Generate PDF using DomPDF
            $pdf = PdfFacade::loadView('memberships.card-pdf', [
                'subscription' => $subscription,
                'qrCodeBase64' => $qrCodeBase64,
            ]);

            // Set paper size to card size (credit card size: 85.60mm x 53.98mm)
            $pdf->setPaper([0, 0, 324, 204], 'portrait'); // ~85.6mm x 53.98mm in points

            // Save to storage
            $filename = "membership-cards/card-{$subscription->subscription_number}.pdf";
            $path = storage_path('app/public/' . $filename);
            
            // Ensure directory exists
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save PDF
            $pdf->save($path);

            // Update subscription with PDF path
            $subscription->card_pdf_path = $filename;
            $subscription->save();

            return $path;
        } catch (\Exception $e) {
            \Log::error('Failed to generate membership card PDF', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get PDF download response
     */
    public function downloadPdf(MembershipSubscription $subscription)
    {
        $path = $this->generatePdf($subscription);
        
        if (!$path || !file_exists($path)) {
            abort(404, 'Card PDF not found');
        }

        return response()->download($path, "Membership-Card-{$subscription->subscription_number}.pdf");
    }

    /**
     * Get PDF stream response
     */
    public function streamPdf(MembershipSubscription $subscription)
    {
        $subscription->load(['membership.business', 'membership.category']);

        // Generate QR code if not exists
        if (!$subscription->qr_code_data) {
            $qrCodeBase64 = $this->generateQrCode($subscription);
            $subscription->qr_code_data = $qrCodeBase64;
            $subscription->save();
        } else {
            $qrCodeBase64 = $subscription->qr_code_data;
        }

        $pdf = PdfFacade::loadView('memberships.card-pdf', [
            'subscription' => $subscription,
            'qrCodeBase64' => $qrCodeBase64,
        ]);

        $pdf->setPaper([0, 0, 324, 204], 'portrait');

        return $pdf->stream("Membership-Card-{$subscription->subscription_number}.pdf");
    }
}
