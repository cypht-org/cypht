<?php

namespace Services\Core\Scheduling;

use Hm_Cache;
use Services\Contracts\Scheduling\Mutex;

class Hm_CacheMutex implements Mutex
{
    private $cache;
    private $expiresAt;

    public function __construct(Hm_Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Create a lock for the task.
     *
     * @param Hm_CommandTask $task The task instance
     * @param int $expiresAt Time in seconds for the lock expiration
     * @return bool Returns true if the lock was created, false otherwise
     */
    public function create($task, $expiresAt)
    {
        $key = $this->getMutexKey($task);

        // Attempt to set the cache key only if it doesn’t exist
        if (!$this->cache->get($key, false)) {
            return $this->cache->set($key, time() + $expiresAt, $expiresAt);
        }

        return false; // Lock already exists
    }

    /**
     * Check if a lock exists for the task.
     *
     * @param Hm_CommandTask $task The task instance
     * @return bool
     */
    public function exists($task)
    {
        $key = $this->getMutexKey($task);
        $lockExpiry = $this->cache->get($key, false);
        return $lockExpiry && $lockExpiry > time();
    }

    /**
     * Release the lock for the task.
     *
     * @param Hm_CommandTask $task The task instance
     * @return void
     */
    public function release($task)
    {
        $key = $this->getMutexKey($task);
        $this->cache->del($key);
    }

    /**
     * Generate a unique key for the mutex.
     *
     * @param Hm_CommandTask $task The task instance
     * @return string
     */
    private function getMutexKey($task)
    {
        // return 'mutex_' . hash('sha256', get_class($task) . $task->name . json_encode($task->command));
        return 'mutex_' . hash('sha256', $task->name);
    }

    /**
     * Refresh the mutex lock expiry if it's still active.
     *
     * @param Task $task The task instance
     * @return void
     */
    public function refresh($task)
    {
        $key = $this->getMutexKey($task);
        if ($this->cache->get($key, false)) {
            $this->cache->set($key, time() + $this->expiresAt, $this->expiresAt);
        }
    }
}
