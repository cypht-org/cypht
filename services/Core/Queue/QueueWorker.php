<?php

namespace Services\Core\Queue;

use Services\Contracts\Queue\ShouldQueue;

/**
 * Class Hm_QueueWorker
 * @package Services\Queue
 */
class QueueWorker
{
    protected ShouldQueue $queue;

    /**
     * Hm_QueueWorker constructor.
     * @param Hm_ShouldQueue $queue
     */
    public function __construct(ShouldQueue $queue)
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
                // exit(var_dump($this->queue));
                $this->queue->process($item);
            } catch (\Exception $e) {
                $item->failed();
                // // Optionally release the job back to the queue with a delay
                // $this->queue->release($job, 30); 
            }
        }
    }
}
