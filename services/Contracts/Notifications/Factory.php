<?php

namespace Services\Contracts\Notifications;

use Services\Core\Notifications\Notification;

interface Factory
{
    /**
     * Get a channel instance by name.
     *
     * @param  string  $name
     * @return mixed
     */
    static public function channel(string $name);

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  mixed  $notification
     * @return void
     */
    static public function send(Notification $notification): void;

    /**
     * Send the given notification immediately.
     *
     * @param  mixed  $notification
     * @return void
     */
    static public function sendNow(Notification $notification): void;
}
