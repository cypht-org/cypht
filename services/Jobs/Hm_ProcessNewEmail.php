<?php

namespace Services\Jobs;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Traits\Hm_EventDispatchable;
use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Events\Hm_NewEmailProcessedEvent;

class Hm_ProcessNewEmail extends Hm_BaseJob //implements Hm_ShouldQueue
{
    use Hm_EventDispatchable;//Hm_InteractsWithQueue;

    protected string $driver = 'database';

    public function __construct(public string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        //fetch new email
        //then process it
        (new Hm_NewEmailProcessedEvent($this->email))->dispatch();
        
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
}
