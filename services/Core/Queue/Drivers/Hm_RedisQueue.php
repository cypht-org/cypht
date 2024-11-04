<?php

namespace Services\Core\Queue\Drivers;

use Hm_Redis;
use Exception;
use Redis;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Core\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;

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
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void 
    {
        if ($this->redis->is_active()) {
            try {
                $serializedJob = serialize($job);
                $this->redisConnection->rpush($this->currentQueue, $serializedJob);
            } catch (Exception $e) {
                throw new Exception("Failed to push job to the queue: " . $e->getMessage());
            }
        }
    }

    /**
     * Pop a job from the current queue
     * we are usig lpop to get the first job in the queue and remove it
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob {
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
     * @param Hm_BaseJob $job
     * @param int $delay
     * @return void
     */
    public function release(Hm_BaseJob $job, int $delay = 0): void {
        if ($this->redis->is_active()) {
            if ($delay > 0) {
                sleep($delay);
            }
            $this->push($job);
        }
    }

    /**
     * Process a job with failure handling
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    public function process(Hm_BaseJob $job): void {
        try {
            $job->handle();
        } catch (Exception $e) {
            $job->incrementAttempts();
            if ($job->getAttempts() >= $job->tries) {
                $this->fail($job, $e);
            } else {
                $this->release($job, 5);
            }
        }
    }

    /**
     * Move a job to the failed jobs queue after max attempts
     *
     * @param Hm_BaseJob $job
     * @param Exception $exception
     * @return void
     */
    public function fail(Hm_BaseJob $job, Exception $exception): void {
        if ($this->redis->is_active()) {
            $failedJobData = [
                'job' => serialize($job),
                'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'exception' => $exception->getMessage()
            ];
            $this->redisConnection->rpush($this->failedQueue, serialize($failedJobData));
        }
    }

    /**
     * Retry a failed job by moving it back to the current queue
     *
     * @return void
     */
    public function retryFailedJobs(): void {
        if ($this->redis->is_active()) {
            while ($failedJobData = $this->redisConnection->lpop($this->failedQueue)) {
                $failedJobRecord = unserialize($failedJobData);
                $job = unserialize($failedJobRecord['job']);

                // Reset attempts and move back to current queue
                $job->resetAttempts();
                $this->push($job);
            }
        }
    }
}
