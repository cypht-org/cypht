<?php

namespace Services\Notifications;

use Services\Traits\Dispatchable;
use Services\Traits\InteractsWithQueue;
use Services\Contracts\Queue\ShouldQueue;
use Services\Core\Notifications\Notification;

class NewMailNotification extends Notification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    public string $driver = 'database';

    public function via(): array
    {
        return ['telegram','slack'];//, 'slack', 'telegram','broadcast'
    }
}
