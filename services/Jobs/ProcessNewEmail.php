<?php

namespace Services\Jobs;

use Services\Core\Jobs\BaseJob;
use Services\Traits\Serializes;
use Services\Traits\Dispatchable;
use Services\Traits\InteractsWithQueue;
use Services\Contracts\Queue\ShouldQueue;
use Services\Events\NewEmailProcessedEvent;

class ProcessNewEmail extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Serializes;

    public string $email;
    /**
     * The queue driver
     */
    public string $driver = 'redis';

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        //fetch new email
        // $imap = Hm_Container::getContainer()->get('imap');//Hm_Imap::class
        // $newMessages = $imap->search('UNSEEN'); 
        //then process it

        dump("Processing ".self::class);

        NewEmailProcessedEvent::dispatch($this->email);
        
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
    public function process(BaseJob $job): void
    {
        
    }
}
