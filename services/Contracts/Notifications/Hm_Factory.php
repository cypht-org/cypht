<?php

namespace Services\Contracts\Notifications;

use Services\Core\Notifications\Hm_Notification;

interface Hm_Factory
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
    static public function send(Hm_Notification $notification): void;

    /**
     * Send the given notification immediately.
     *
     * @param  mixed  $notification
     * @return void
     */
    static public function sendNow(Hm_Notification $notification): void;
}
