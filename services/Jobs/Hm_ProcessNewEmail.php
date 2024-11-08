<?php

namespace Services\Jobs;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Traits\Hm_Serializes;
use Services\Traits\Hm_Dispatchable;
use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Events\Hm_NewEmailProcessedEvent;

class Hm_ProcessNewEmail extends Hm_BaseJob implements Hm_ShouldQueue
{
    use Hm_Dispatchable, Hm_InteractsWithQueue, Hm_Serializes;

    public string $email;

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

        Hm_NewEmailProcessedEvent::dispatch($this->email);
        
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
    public function process(Hm_BaseJob $job): void
    {
        
    }
}
