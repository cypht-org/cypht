<?php

namespace Services\Providers;

use Hm_Cache;
use Services\Core\Scheduling\CacheMutex;
use Services\Core\Scheduling\Scheduler;

class Hm_SchedulerServiceProvider
{

    /**
     * Register and initialize the Scheduler and dependencies.
     *
     * @return Scheduler
     */
    public function register($config, $session)
    {
        // Initialize Hm_Cache
        $cache = new Hm_Cache($config, $session);

        // Create the CacheMutex instance using the cache
        $mutex = new CacheMutex($cache);

        // Create the Scheduler instance, passing in the CacheMutex
        $scheduler = new Scheduler($mutex);

        // Register scheduled tasks here (optional setup)
        // Example:
        // $scheduler->command('check:mail')->everyMinute()->withoutOverlapping(10);

        return $scheduler;
    }
}
