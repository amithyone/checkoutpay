<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class RenterEmailVerificationNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'rentals.verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );

        // Generate 6-digit verification PIN
        $verificationPin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store PIN in cache for 60 minutes
        \Illuminate\Support\Facades\Cache::put(
            'renter_email_verification_pin_' . $notifiable->getKey(),
            $verificationPin,
            now()->addMinutes(60)
        );

        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('Verify Your Email Address - ' . $appName)
            ->view('emails.renter-verification', [
                'renter' => $notifiable,
                'verificationUrl' => $verificationUrl,
                'verificationPin' => $verificationPin,
                'appName' => $appName,
            ]);
    }
}
