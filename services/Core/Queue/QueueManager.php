<?php

namespace Services\Core\Queue;

use Services\Contracts\Queue\ShouldQueue;

/**
 * Class Hm_QueueManager
 * @package Services\Queue
 */
class QueueManager
{
    protected array $drivers;

    /**
     * Hm_QueueManager constructor.
     * @param array $drivers
     */
    public function addDriver(string $name, ShouldQueue $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function getDriver(string $name): ShouldQueue
    {
        return $this->drivers[$name];
    }
}
