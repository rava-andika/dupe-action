<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;

class EmailSentListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSending $event): bool
    {
        $TodaySend = Cache::get('today_email_sent', 0);

        //get the mailer
        $mailerIdx = floor($TodaySend / config('mail.daily_send_limit'));
        $mailerUser = explode(',', config('mail.mailers.smtp.username'))[$mailerIdx] ?? null;
        $mailerPass = explode(',', config('mail.mailers.smtp.password'))[$mailerIdx] ?? null;

        if ($mailerUser && $mailerPass) {
            $transportDsn = sprintf(
                '%s://%s:%s@%s:%s%s',
                config('mail.default'),
                $mailerUser,
                $mailerPass,
                config('mail.mailers.smtp.host'),
                config('mail.mailers.smtp.port'),
                '?keepalive=true'
            );
            $transport = Transport::fromDsn($transportDsn);
            $mailer = new Mailer($transport);

            $event->message->from(new Address($mailerUser, config('mail.from.name')));
            $mailer->send($event->message);

            // save log and make it expire in 24 hours
            Cache::put('today_email_sent', $TodaySend + 1, now()->addDays(1));
        } else {
            throw new \Exception('Daily email limit exceeded');
        }

        return false; // stop the event
    }
}
