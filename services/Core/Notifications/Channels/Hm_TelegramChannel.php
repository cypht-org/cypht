<?php

namespace Services\Core\Notifications\Channels;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;

class Hm_TelegramChannel extends Hm_NotificationChannel
{
    private TelegramTransport $transport;

    public function __construct(TelegramTransport $transport)
    {
        $this->transport = $transport;
    }

    public function send($notifiable, string $message): void
    {
        $notification = (new Notification('Telegram Notification'))
            ->content($message);
        
        $this->transport->send($notification);
    }
}
