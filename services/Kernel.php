<?php

namespace Services;

use Services\Core\Scheduling\Scheduler;

class Kernel
{
    protected $scheduler;

    public function __construct(Scheduler $scheduler)
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
