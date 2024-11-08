<?php

namespace Services\Core\Queue;

use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Class Hm_QueueWorker
 * @package Services\Queue
 */
class Hm_QueueWorker
{
    protected Hm_ShouldQueue $queue;

    /**
     * Hm_QueueWorker constructor.
     * @param Hm_ShouldQueue $queue
     */
    public function __construct(Hm_ShouldQueue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Work the queue
     *
     * @return void
     */
    public function work(): void {
        while ($job = $this->queue->pop())
        {
            try {
                $this->queue->process($job);
            } catch (\Exception $e) {
                // $job->failed();
                // // Optionally release the job back to the queue with a delay
                // $this->queue->release($job, 30); 
            }
        }
    }
}
