<?php

namespace Services\Core\Queue;

use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Class Hm_QueueManager
 * @package Services\Queue
 */
class Hm_QueueManager
{
    protected array $drivers;

    /**
     * Hm_QueueManager constructor.
     * @param array $drivers
     */
    public function addDriver(string $name, Hm_ShouldQueue $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function getDriver(string $name): Hm_ShouldQueue
    {
        dump("Getting driver $name");
        return $this->drivers[$name];
    }
}
