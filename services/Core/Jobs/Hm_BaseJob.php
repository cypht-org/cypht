<?php

namespace Services\Core\Jobs;

use Services\Contracts\Hm_Job;

abstract class Hm_BaseJob implements Hm_Job
{
    public string $driver = '';
    public int $tries = 3;
    protected int $attempts = 0;
    
    public function __construct(protected array $data = []) {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void {}
    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(): void {}

    /**
     * Get the driver name for the job.
     *
     * @return int
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Method to increment the attempt count
     *
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * Determine if the job has exceeded the maximum number of attempts.
     *
     * @return bool
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->tries;
    }

}
