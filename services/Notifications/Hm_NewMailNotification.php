<?php

namespace Services\Notifications;

use Services\Traits\Hm_Dispatchable;
use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Notifications\Hm_Notification;

class Hm_NewMailNotification extends Hm_Notification implements Hm_ShouldQueue
{
    use Hm_Dispatchable, Hm_InteractsWithQueue;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    public function via(): array
    {
        return ['slack', 'telegram','broadcast'];
    }
}
