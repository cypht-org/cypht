<?php

namespace Services\Core\Notifications\Channels;

abstract class Hm_NotificationChannel
{
    abstract public function send($notifiable, string $message): void;
}
