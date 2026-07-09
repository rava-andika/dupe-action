<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends VerifyEmail
{
    public function __construct(protected string|null $redirectUrl = null)
    {}

    /**
     * Get the mail representation of the notification.
     * 
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $expireTime = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',                      
            now()->addMinutes($expireTime),                       
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'locale' => app()->getLocale(),
                'redirect_url' => $this->redirectUrl
            ]
        );
        $translation = app('translations')['mail-verification'];
        return (new MailMessage)
            ->subject($translation['subject'])
            ->markdown('mail.send-email-verification', [
                'url' => $verificationUrl,
                'name' => $notifiable->name,
                'translation' => $translation,
            ]);
    }
}
