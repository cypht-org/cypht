<?php

namespace Services\Jobs;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Traits\Hm_Serializes;
use Services\Traits\Hm_Dispatchable;
use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Hm_Container;
use Services\Events\Hm_NewEmailProcessedEvent;
use Services\ImapConnectionManager;

class Hm_ProcessNewEmail extends Hm_BaseJob implements Hm_ShouldQueue
{
    use Hm_Dispatchable, Hm_InteractsWithQueue, Hm_Serializes;

    public string $email;
    /**
     * The queue driver
     */
    public string $driver = 'database';

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
                print("start {$this->email} \n");

        //fetch new email
        $servers = Hm_Container::getContainer()->get(ImapConnectionManager::class);//Hm_Imap::class
        // dump($servers);
                dump($servers);die();

        // $newMessages = $imap->search('UNSEEN'); 
        //then process it
        print("Counting \n");
        // $count = count($servers->getAll());
        // print("get server:s {$count}\n");

        // Hm_NewEmailProcessedEvent::dispatch($this->email);
        
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
    public function process(Hm_BaseJob $job): void
    {
        
    }
}
