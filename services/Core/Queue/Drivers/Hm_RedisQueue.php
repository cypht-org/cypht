<?php

namespace Services\Core\Queue\Drivers;

use Redis;
use Hm_Redis;
use Exception;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Notifications\Hm_Notification;
use Services\Core\Queue\Hm_Queueable as Hm_QueueableClass;

/**
 * Redis Queue using Hm_Redis with Hm_Cache_Base trait Hm_RedisQueue
 */
class Hm_RedisQueue implements Hm_ShouldQueue, Hm_Queueable
{
    protected Hm_Redis $redis;
    protected Redis $redisConnection;

    /**
     * Queue name for active jobs
     *
     * @var string
     */
    protected string $currentQueue;

    /**
     * Queue name for failed jobs
     *
     * @var string
     */
    protected string $failedQueue;

    /**
     * Constructor
     *
     * @param $redisConnection
     * @param string $queue
     */
    public function __construct(Hm_Redis $redis, Redis $redisConnection, string $queue = 'default') {
        $this->redis = $redis;
        $this->redisConnection = $redisConnection;
        $this->currentQueue = "hm_jobs:{$queue}_jobs";
        $this->failedQueue = "hm_jobs:{$queue}_failed";
    }

    /**
     * Push a job to the current queue
     *
     * @param Hm_QueueableClass $item
     * @return void
     */
    public function push($item): void 
    {
        if ($this->redis->is_active()) {
            try {
                $serializedItem = serialize($item);
                $this->redisConnection->rpush($this->currentQueue, $serializedItem);
            } catch (Exception $e) {
                throw new Exception("Failed to push job to the queue: " . $e->getMessage());
            }
        }
    }

    /**
     * Pop a job from the current queue
     * we are usig lpop to get the first job in the queue and remove it
     *
     * @return Hm_QueueableClass|null
     */
    public function pop(): ?Hm_QueueableClass {
        if ($this->redis->is_active()) {
            $jobData = $this->redisConnection->lpop($this->currentQueue);
            if ($jobData) {
                $job = unserialize($jobData);
                $job->incrementAttempts();
                return $job;
            }
        }              

        return null;
    }

    /**
     * Release a job back into the current queue after a delay
     *
     * @param Hm_QueueableClass $job
     * @param int $delay
     * @return void
     */
    public function release(Hm_QueueableClass $item, int $delay = 0): void {
        if ($this->redis->is_active()) {
            if ($delay > 0) {
                sleep($delay);
            }
            $this->push($item);
        }
    }

    /**
     * Process a job with failure handling
     *
     * @param Hm_QueueableClass $item
     * @return void
     */
    public function process(Hm_QueueableClass $item): void {
        try {
            // Check if the item is a notification, if so send it
            if($item instanceof Hm_Notification) {
                $item->send();
            }else {
                // Otherwise handle the job
                $item->handle();
            }
        } catch (Exception $e) {
            $item->incrementAttempts();
            if ($item->getAttempts() >= $item->tries) {
                $this->fail($item, $e);
            } else {
                $this->release($item, 5);
            }
        }
    }

    /**
     * Move a job to the failed jobs queue after max attempts
     *
     * @param Hm_QueueableClass $job
     * @param Exception $exception
     * @return void
     */
    public function fail(Hm_QueueableClass $item, Exception $exception): void {
        if ($this->redis->is_active()) {
            $failedItemData = [
                'payload' => serialize($item),
                'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'exception' => $exception->getMessage()
            ];
            $this->redisConnection->rpush($this->failedQueue, serialize($failedItemData));
        }
    }

    /**
     * Retry a failed job by moving it back to the current queue
     *
     * @return void
     */
    public function retryFailedJobs(): void {
        if ($this->redis->is_active()) {
            while ($failedItemData = $this->redisConnection->lpop($this->failedQueue)) {
                $failedItemRecord = unserialize($failedItemData);
                $item = unserialize($failedItemRecord['payload']);

                // Reset attempts and move back to current queue
                $item->resetAttempts();
                $this->push($item);
            }
        }
    }
}
