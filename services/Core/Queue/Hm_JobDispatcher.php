<?php

namespace Services\Core\Queue;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Hm_Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Hm_JobDispatcher
 * @package Services\Queue
 */
class Hm_JobDispatcher
{
    /**
     * Dispatch the job to the queue
     *
     * @param Hm_BaseJob $job
     * @param string|null $queue
     * @return void
     */
    static public function dispatch(Hm_BaseJob $job): void {
        if (is_subclass_of($job, Hm_ShouldQueue::class)) {
            $driver = $job->driver;
            dd($driver);
            $queueDriver = Hm_Container::getContainer()->get('queue.manager')->getDriver($driver);
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