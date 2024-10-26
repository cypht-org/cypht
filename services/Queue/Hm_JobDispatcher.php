<?php

namespace Services\Queue;

use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Jobs\Hm_BaseJob;

/**
 * Class Hm_JobDispatcher
 * @package Services\Queue
 */
class Hm_JobDispatcher
{
    protected Hm_QueueManager $queueManager;
    protected string $defaultDriver;

    /**
     * Hm_JobDispatcher constructor.
     * @param Hm_QueueManager $queueManager
     * @param string $defaultDriver
     */
    public function __construct(Hm_QueueManager $queueManager, string $defaultDriver = 'redis')
    {
        $this->queueManager = $queueManager;
        $this->defaultDriver = $defaultDriver;
    }

    /**
     * Dispatch the job to the queue
     *
     * @param Hm_BaseJob $job
     * @param string|null $queue
     * @return void
     */
    public function dispatch(Hm_BaseJob $job, string $queue = null): void {
        if ($job instanceof Hm_ShouldQueue) {
            $driver = $job->driver ?? $this->defaultDriver;
            $queueDriver = $this->queueManager->getDriver($driver);

            if ($queueDriver) {
                $queueDriver->push($job);
            } else {
                throw new \Exception("Queue driver {$driver} not found.");
            }
        }else {
            $job->handle();
        }
        // $driver = $this->queueManager->getDriver($queue ?? $this->defaultDriver);
        // $driver->push($job);
    }
}