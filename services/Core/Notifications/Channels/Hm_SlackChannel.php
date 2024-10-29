<?php

namespace Services\Core\Notifications\Channels;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;

class Hm_SlackChannel extends Hm_NotificationChannel
{
    private SlackTransport $transport;

    public function __construct(SlackTransport $transport)
    {
        $this->transport = $transport;
    }

    public function send($notifiable, string $message): void
    {
        // Assuming $notifiable has a method to get the Slack channel/user ID
        $notification = (new Notification('Slack Notification'))
            ->content($message);
        
        $this->transport->send($notification);
    }
}
