<?php

namespace Services\Queue\Drivers;

use Hm_Redis;
use Services\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Redis Queue
 */
class Hm_RedisQueue implements Hm_ShouldQueue
{
    /**
     * @var Hm_Redis
     */
    protected Hm_Redis $redis;

    /**
     * @var string
     */
    protected string $queue;

    /**
     * Constructor
     *
     * @param Hm_Redis $redis
     * @param string $queue
     */
    public function __construct(Hm_Redis $redis, string $queue = 'default') {
        $this->redis = $redis;
        $this->queue = $queue;
    }

    /**
     * Push the job to the queue
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void {
        // Use the Redis connection to push the serialized job to the queue
        $this->redis->getConnection()->rpush($this->queue, serialize($job));
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob {
        $jobData = $this->redis->getConnection()->lpop($this->queue);
        return $jobData ? unserialize($jobData) : null;
    }

    /**
     * Release the job back into the queue
     *
     * @param Hm_BaseJob $job
     * @param int $delay
     * @return void
     */
    public function release(Hm_BaseJob $job, int $delay = 0): void {
        if ($delay > 0) {
            sleep($delay);
        }
        $this->push($job);
    }
}
