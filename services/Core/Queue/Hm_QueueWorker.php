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
        while ($item = $this->queue->pop())
        {
            try {
                $this->queue->process($item);
            } catch (\Exception $e) {
                $item->failed();
                // // Optionally release the job back to the queue with a delay
                // $this->queue->release($job, 30); 
            }
        }
    }
}
