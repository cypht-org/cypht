<?php

namespace Services\Core\Scheduling;

class CommandTask extends ScheduledTask
{
    private $command;
    private $onOneServer = false;
    private $mutex;
    private $expiresAt = null;
    private $withoutOverlapping = false;

    public function __construct($command, CacheMutex $mutex)
    {
        $fullCommand = "php console " . $command;
        parent::__construct(function () use ($fullCommand) {
            echo "Executing Command: $fullCommand\n";
            $output = shell_exec($fullCommand);
            echo $output;
        }, $command);

        $this->command = $fullCommand;
        $this->mutex = $mutex;
    }

    /**
     * Prevent command overlap by using a cache-based mutex.
     *
     * @param int $expiresAt Expiration time in seconds
     * @return $this
     */
    public function withoutOverlapping($expiresAt = 1440)
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;

        // Skip execution if mutex exists
        return $this->skip(function () {
            return $this->mutex->exists($this);
        });
    }

    /**
     * Run the command if it’s due and not overlapping.
     */
    public function run()
    {
        if ($this->isDue() && (!$this->withoutOverlapping || $this->acquireMutex())) {
            parent::run(); // Executes task
            if ($this->withoutOverlapping) {
                $this->releaseMutex();
            }
        }
    }

    /**
     * Acquire the mutex to prevent overlapping.
     *
     * @return bool
     */
    private function acquireMutex()
    {
        return $this->mutex->create($this, $this->expiresAt);
    }

    /**
     * Release the mutex after execution.
     */
    private function releaseMutex()
    {
        $this->mutex->release($this);
    }
}
