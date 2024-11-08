<?php

namespace Services\Core\Scheduling;

use Services\Traits\Hm_ScheduleFrequencyManager;

class Hm_ScheduledTask
{
    use Hm_ScheduleFrequencyManager;

    private $callback;
    private $nextRunTime;
    private $isEnabled = true;
    private $name;
    private $description;
    private $tags = [];
    private $lastRunTime;

    private $maxRetries = 3;
    private $retryInterval = 60; // Interval in seconds between retries
    private $retryCount = 0; // Track the number of retries attempted

    public function __construct(callable $callback, $name = '', $description = '', $tags = [], $timezone = 'UTC', $expression = '* * * * *')
    {
        $this->callback = $callback;
        $this->name = $name;
        $this->description = $description;
        $this->tags = $tags;
        $this->timezone = $timezone;
        $this->expression = $expression;
    }

    public function getName()
    {   
        return $this->name;
    }

    public function isDue()
    {   
        if ($this->isEnabled) {
            $this->calculateNextRunTime();
            return $this->nextRunTime <= new \DateTime('now', new \DateTimeZone($this->timezone));
        }
        return false;
    }

    public function run()
    {
        if (!$this->isDue()) {
            return;
        }

        try {
            echo "Task '{$this->name}' is due and will be run.\n"; 

            // Run the task callback
            call_user_func($this->callback);
            $this->lastRunTime = new \DateTime('now', new \DateTimeZone($this->timezone));

            // Reset retry count after a successful run
            $this->retryCount = 0;
        } catch (\Exception $e) {
            echo "Error running task {$this->name}: " . $e->getMessage() . "\n"; 

            // Log the error message
            error_log("Error running task {$this->name}: " . $e->getMessage());

            // Handle retries
            $this->handleRetry($e);
        }

        $this->scheduleNextRun();
    }

    private function handleRetry(\Exception $e)
    {
        if ($this->retryCount < $this->maxRetries) {
            $this->retryCount++;
            $retryTime = $this->retryInterval * $this->retryCount;

            // Log the retry attempt
            error_log("Retry attempt {$this->retryCount} for task {$this->name}, will retry in {$retryTime} seconds.");
            sleep($retryTime);

            // Try again
            $this->run();
        } else {
            // Log that we've exhausted all retries
            error_log("Max retries reached for task {$this->name}. Task will not be retried.");
        }
    }

    private function scheduleNextRun()
    {
        // You can schedule the next run based on cron expression or your custom logic
        $this->nextRunTime = $this->calculateNextRunTime();
    }

    private function calculateNextRunTime()
    {
        // Ensure the cron expression is valid
        if (empty($this->expression)) {
            throw new \InvalidArgumentException("Cron expression must be set.");
        }

        // Split the cron expression into parts
        $parts = preg_split('/\s+/', $this->expression);
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Invalid cron expression: {$this->expression}");
        }

        // Extract the cron fields
        list($minuteField, $hourField, $dayOfMonthField, $monthField, $dayOfWeekField) = $parts;

        $now = new \DateTime('now', new \DateTimeZone($this->timezone));
        $next = clone $now;

        // Calculate next minute
        $nextMinute = $this->getNextFieldValue($next->format('i'), $minuteField, 0, 59);

        if ($nextMinute !== null) {

            $next->setTime($next->format('H'), $nextMinute);
        } else {
            $next->modify('+1 hour');
            $next->setTime(0, $this->getNextFieldValue(0, $minuteField, 0, 59));
        }
        
        // Calculate next hour
        $nextHour = $this->getNextFieldValue($next->format('H'), $hourField, 0, 23);
        if ($nextHour !== null) {
            $next->setTime($nextHour, $next->format('i'));
        } else {
            $next->modify('+1 day');
            $next->setTime(0, $this->getNextFieldValue(0, $minuteField, 0, 59));
        }

        // Calculate next day of the month
        $nextDay = $this->getNextFieldValue($next->format('d'), $dayOfMonthField, 1, 31);
        if ($nextDay !== null) {
            $next->setDate($next->format('Y'), $next->format('m'), $nextDay);
        } else {
            $next->modify('+1 month');
            $next->setDate($next->format('Y'), $next->format('m'), $this->getNextFieldValue(1, $dayOfMonthField, 1, 31));
        }

        // Calculate next month
        $nextMonth = $this->getNextFieldValue($next->format('n'), $monthField, 1, 12);
        if ($nextMonth !== null) {
            $next->setDate($next->format('Y'), $nextMonth, $next->format('d'));
        } else {
            $next->modify('+1 year');
            $next->setDate($next->format('Y'), $this->getNextFieldValue(1, $monthField, 1, 12), $next->format('d'));
        }

        // Calculate next day of the week
        $nextDayOfWeek = $this->getNextFieldValue($next->format('w'), $dayOfWeekField, 0, 6);

        if ($nextDayOfWeek !== null) {
            while (intval($next->format('w')) !== $nextDayOfWeek) {
                $next->modify('+1 day');
            }
        } else {
            $next->modify('+1 week');
        }

        return $next;
    }

    private function getNextFieldValue($currentValue, $field, $min, $max)
    {
        $values = [];
    
        if ($field === '*') {
            // If field is '*', we want the next value in sequence, wrapping if needed
            if ($currentValue < $max) {
                return $currentValue + 1; // Move to next minute, hour, etc.
            } else {
                return $min; // Wrap around to the minimum (e.g., new hour if on minute field)
            }
        }
    
        foreach (explode(',', $field) as $part) {
            if (strpos($part, '/') !== false) {
                // Handle step values, e.g., */2
                list($base, $step) = explode('/', $part);
                if ($base === '*') {
                    for ($i = $min; $i <= $max; $i += $step) {
                        $values[] = $i;
                    }
                }
            } elseif (strpos($part, '-') !== false) {
                // Handle ranges, e.g., 1-5
                list($rangeStart, $rangeEnd) = explode('-', $part);
                for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
                    $values[] = (int)$i;
                }
            } else {
                // Single values
                $values[] = (int)$part;
            }
        }
    
        // Filter, sort, and keep unique values
        $values = array_unique(array_filter($values, function ($value) use ($min, $max) {
            return $value >= $min && $value <= $max;
        }));
        sort($values);
    
        // Find the next valid value that is greater than the current value
        foreach ($values as $value) {
            if ($value > $currentValue) {
                return $value;
            }
        }
    
        // If no valid next value is found, wrap around to the first value in the list
        return !empty($values) ? $values[0] : $min;
    }
    
}
