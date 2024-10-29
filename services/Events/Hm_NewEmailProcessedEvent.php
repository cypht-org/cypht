<?php

namespace Services\Events;

use Services\Core\Events\Hm_BaseEvent;
use Services\Core\Events\Hm_EventManager;

class Hm_NewEmailProcessedEvent extends Hm_BaseEvent
{
    public function __construct(public string $email)
    {
        parent::__construct($email);
    }

    public function dispatch(): void
    {
        Hm_EventManager::dispatch($this);
    }
}
