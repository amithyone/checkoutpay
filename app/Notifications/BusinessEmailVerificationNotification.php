<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class BusinessEmailVerificationNotification extends Notification
{
    use Queueable;

    public function __construct()
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'business.verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );

        $verificationPin = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            'email_verification_pin_'.$notifiable->getKey(),
            $verificationPin,
            now()->addMinutes(60)
        );

        $appName = Setting::get('site_name', 'CheckoutPay');

        return EmailTemplateService::toMailMessage(
            'business-verification',
            'emails.business-verification',
            'Verify Your Email Address - '.$appName,
            [
                'business' => $notifiable,
                'verificationUrl' => $verificationUrl,
                'verificationPin' => $verificationPin,
                'appName' => $appName,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
