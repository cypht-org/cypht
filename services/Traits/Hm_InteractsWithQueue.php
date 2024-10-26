<?php

namespace Services\Traits;

use Services\Contracts\Hm_Job;
use Services\Queue\Hm_QueueManager;
use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Trait Hm_InteractsWithQueue
 */
trait Hm_InteractsWithQueue
{
     /**
     * The underlying queue job instance.
     *
     */
    public $job;
    /**
     * Push a job onto the queue.
     *
     * @param Hm_ShouldQueue $job The job to be pushed onto the queue.
     * @param mixed $data Optional data to pass with the job.
     * @param string|null $queue Optional name of the queue.
     * @return void
     */
    public function push(Hm_Job $job, $data = '', $queue = null)
    {
        $driver = $job->getDriver();

        // Call the appropriate method from the QueueManager to push the job
        (new Hm_QueueManager)->getDriver($driver)->push($this, $data, $queue);
        
        echo "Job of type " . get_class($job) . " pushed to the queue successfully.\n";
    }

    /**
     * Pop a job from the queue.
     *
     * @param string|null $queue Optional name of the queue.
     * @return mixed The job from the queue or null if the queue is empty.
     */
    public function pop($queue = null)
    {
        $driver = $this->getDriver();

        // Call the appropriate method from the QueueManager to pop a job
        $job = (new Hm_QueueManager)->getDriver($driver)->pop($queue);

        if ($job) {
            echo "Job of type " . get_class($job) . " popped from the queue.\n";
        } else {
            echo "No job available in the queue.\n";
        }

        return $job;
    }

    /**
     * Release a job back onto the queue with an optional delay.
     *
     * @param string|null $queue Optional name of the queue.
     * @param int $delay The number of seconds to delay the release.
     * @return void
     */
    public function release($queue = null, $delay = 0)
    {
        $driver = $this->getDriver();

        (new Hm_QueueManager)->getDriver($driver)->release($this, $queue, $delay);
        
        // echo "Job of type " . get_class($this) . " released back to the queue with a delay of {$delay} seconds.\n";
    }
}
