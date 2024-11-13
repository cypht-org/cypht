<?php

namespace Services\Core\Queue;

class Hm_Queueable
{
    /**
     * The name of the connection the item should be sent to.
     *
     * @var string
     */
    public string $driver = '';

    /**
     * The number of times the item may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of times the item has been attempted.
     *
     * @var int
     */
    public int $attempts = 0;
    
    /**
     * Execute the item.
     *
     * @return void
     */
    public function handle(): void {}

    /**
     * Handle a item failure.
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
