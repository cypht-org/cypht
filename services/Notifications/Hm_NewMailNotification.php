<?php

namespace Services\Notifications;

use Services\Core\Notifications\Hm_Notification;

class Hm_NewMailNotification extends Hm_Notification
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    public function via(): array
    {
        // Specify which channels this notification should use
        return ['slack', 'telegram'];
    }

    public function sendNotification($notifiable, string $message): void
    {
        // You can define a title and content as needed
        $title = "New Notification";
        $this->send($notifiable, $title, $message);
    }
}
