<?php

namespace Services\Jobs;

use Services\Traits\Hm_InteractsWithQueue;
use Services\Contracts\Queue\Hm_ShouldQueue;

class Hm_ProcessNewEmail extends Hm_BaseJob implements Hm_ShouldQueue
{
    use Hm_InteractsWithQueue;

    protected string $driver = 'database';

    public function __construct(public string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        print("Processing email for {$this->email}\n");
    }

    public function failed(): void
    {
        print("Failed to process email for {$this->email}\n");
    }
}
