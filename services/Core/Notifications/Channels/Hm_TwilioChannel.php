<?php

namespace Services\Core\Notifications\Channels;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransport;

class Hm_TwilioChannel extends Hm_NotificationChannel
{
    private TwilioTransport $transport;

    public function __construct(TwilioTransport $transport)
    {
        $this->transport = $transport;
    }

    public function send($notifiable, string $message): void
    {
        $notification = (new Notification('SMS Notification'))
            ->content($message);
        
        $this->transport->send($notification);
    }
}
