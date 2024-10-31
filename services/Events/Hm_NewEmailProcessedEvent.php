<?php

namespace Services\Events;

use Services\Core\Events\Hm_BaseEvent;
use Services\Traits\Hm_Dispatchable;

class Hm_NewEmailProcessedEvent extends Hm_BaseEvent
{
    use Hm_Dispatchable;
}
