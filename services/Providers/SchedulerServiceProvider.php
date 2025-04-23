<?php

namespace Services\Providers;

use Hm_Cache;
use Services\Core\Container;
use Services\Core\Scheduling\Scheduler;
use Services\Core\Scheduling\CacheMutex;

/**
 * Class Hm_SchedulerServiceProvider
 * @package Services\Providers
 */
class SchedulerServiceProvider
{

    /**
     * Register and initialize the Scheduler and dependencies.
     *
     * @return Scheduler
     */
    public function register($config, $session)
    {
        $containerBuilder = Container::getContainer();
        // Initialize Hm_Cache
        $cache = new Hm_Cache($config, $session);

        // Create the CacheMutex instance using the cache
        $mutex = new CacheMutex($cache);

        // Create the Scheduler instance, passing in the CacheMutex
        $scheduler = new Scheduler($config);

        $containerBuilder->set('scheduler', $scheduler);
        $containerBuilder->set('mutex', $mutex);
        $containerBuilder->set('cache', $cache);
    }
}
