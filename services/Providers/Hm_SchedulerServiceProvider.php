<?php

namespace Services\Providers;

use Hm_Cache;
use Services\Core\Hm_Container;
use Services\Core\Scheduling\Hm_CacheMutex;
use Services\Core\Scheduling\Hm_Scheduler;

class Hm_SchedulerServiceProvider
{

    /**
     * Register and initialize the Scheduler and dependencies.
     *
     * @return Scheduler
     */
    public function register($config, $session)
    {
        $containerBuilder = Hm_Container::getContainer();
        // Initialize Hm_Cache
        $cache = new Hm_Cache($config, $session);

        // Create the CacheMutex instance using the cache
        $mutex = new Hm_CacheMutex($cache);

        // Create the Scheduler instance, passing in the CacheMutex
        $scheduler = new Hm_Scheduler($config);

        $containerBuilder->set('scheduler', $scheduler);
        $containerBuilder->set('mutex', $mutex);
        $containerBuilder->set('cache', $cache);
    }
}
