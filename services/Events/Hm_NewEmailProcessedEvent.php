<?php

namespace Services\Events;

use Services\Traits\Hm_Dispatchable;
use Services\Core\Events\Hm_BaseEvent;

class Hm_NewEmailProcessedEvent extends Hm_BaseEvent// implements Hm_ShouldQueue
{
    use Hm_Dispatchable;//Hm_InteractsWithQueue;

    /**
     * Create a new event instance.
     * @param  $email
     * 
     * @return void
     */
    public function __construct(private string $email)
    {
        $this->email = $email;
    }
}
