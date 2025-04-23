<?php

namespace Services\Core\Notifications\Channels;

abstract class NotificationChannel
{
    abstract public function send($notification): void;
}
