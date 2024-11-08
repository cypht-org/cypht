<?php

namespace Services;

use Services\Core\Scheduling\Hm_Scheduler;

/**
 * Class Hm_ConsoleKernel
 * @package Services
 */
class Hm_ConsoleKernel
{
    /**
     * @var Hm_Scheduler
     */
    protected $scheduler;

    /**
     * Hm_ConsoleKernel constructor.
     * @param Hm_Scheduler $scheduler
     */
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
