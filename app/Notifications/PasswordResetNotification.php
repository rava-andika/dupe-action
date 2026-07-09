<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\ResetPassword;

class PasswordResetNotification extends ResetPassword
{
    /**
     * Get the mail representation of the notification.
     * 
     * @param mixed
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $expireTime = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');
        $resetUrl = URL::temporarySignedRoute(
            'password-reset',
            now()->addMinutes($expireTime),
            [
                'locale' => app()->getLocale(),
                'token' => $this->token,
                'email' => $notifiable->email,
            ]
        );

        $translation = app('translations')['mail-reset-password'];

        return (new MailMessage)
            ->subject($translation['subject'])
            ->markdown('mail.send-password-reset', [
                'url' => $resetUrl,
                'name' => $notifiable->name,
                'translation' => $translation,
            ]);
    }
}
