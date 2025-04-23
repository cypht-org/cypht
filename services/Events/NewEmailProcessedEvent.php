<?php

namespace Services\Events;

use Services\Traits\Dispatchable;
use Services\Core\Events\BaseEvent;

class NewEmailProcessedEvent extends BaseEvent// implements Hm_ShouldQueue
{
    use Dispatchable;//Hm_InteractsWithQueue;

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
