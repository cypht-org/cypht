<?php

namespace Services\Core\Jobs;

use Services\Contracts\Hm_Job;

abstract class Hm_BaseJob implements Hm_Job
{
    public string $driver = 'database';
    public int $tries = 3;
    protected int $attempts = 0;
    
    public function __construct(protected array $data = []) {
        $this->data = $data;
    }

    public function handle(): void {}
    public function failed(): void {}

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    // Method to increment the attempt count
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    // Check if the job has exceeded the max attempts
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->tries;
    }

}
