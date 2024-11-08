<?php

namespace Services;

use Services\Core\Scheduling\Hm_Scheduler;
use Services\Core\Hm_Container;

class Hm_ConsoleKernel
{
    protected $scheduler;

    public function __construct(Hm_Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;

    }

    /**
     * Define the application's command schedule.
     */
    public function schedule()
    {
        // Register tasks with the scheduler
        $this->scheduler->command('check:mail')
            ->everyMinute()
            ->withoutOverlapping();
    }
}
