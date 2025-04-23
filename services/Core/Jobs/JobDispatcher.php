<?php

namespace Services\Core\Jobs;

use Services\Core\Container;
use Services\Core\Jobs\BaseJob;
use Services\Contracts\Queue\ShouldQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Hm_JobDispatcher
 * @package Services\Queue
 */
class JobDispatcher
{
    /**
     * Dispatch the job to the queue
     *
     * @param Hm_BaseJob $job
     * @param string|null $queue
     * @return void
     */
    static public function dispatch(BaseJob $job): void {
        if (is_subclass_of($job, ShouldQueue::class)) {
            $driver = $job->driver;
            $queueDriver = Container::getContainer()->get('queue.manager')->getDriver($driver);
            if ($queueDriver) {
                $queueDriver->push($job);
            } else {
                throw new \Exception("Queue driver {$driver} not found.");
            }
        }else {
            $job->handle();
        }
    }
}