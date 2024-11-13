<?php

namespace Services\Contracts\Notifications;

interface Hm_Dispatcher
{
    /**
     * Send the given notification to the given notifiable entities.
     *
     * @return void
     */
    public function send();

    /**
     * Send the given notification immediately.
     *
     * @return void
     */
    public function sendNow();

    /**
     * Get the channels the notification should broadcast on.
     *
     * @return array
     */
    public function via(): array;
}
