<?php

namespace Services\Jobs;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Traits\Hm_Dispatchable;
use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Events\Hm_NewEmailProcessedEvent;

class Hm_ProcessNewEmail extends Hm_BaseJob implements Hm_ShouldQueue
{
    use Hm_Dispatchable, Hm_InteractsWithQueue;

    protected string $driver = 'database';

    public function __construct(public string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        //fetch new email
        //then process it

        dump('job processing');
        
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
}
