<?php

namespace Services\Core\Scheduling;

use Services\Traits\Hm_ScheduleFrequencyManager;

class Hm_ScheduledTask
{
    use Hm_ScheduleFrequencyManager;

    /**
     * The caallback to run
     *
     */
    private $callback;
    /**
     * The next run time
     *
     * @var string
     */
    private $nextRunTime;
    /**
     * check if the task is enabled
     *
     * @var boolean
     */
    private $isEnabled = true;
    /**
     * The task name
     *
     * @var string
     */
    private $name;
    /**
     * The task name
     *
     * @var string
     */
    private $description;
    /**
     * The task name
     *
     * @var array
     */
    private $tags = [];
    /**
     * The last run time
     *
     */
    private $lastRunTime;
    /**
     * The maximum number of retries
     *
     * @var int
     */
    private int $maxRetries = 3;

    /**
     * Interval in seconds between retries
     *
     * @var int
     */
    private int $retryInterval = 60;
    /**
     * Track the number of retries attempted
     *
     * @var int
     */
    private int $retryCount = 0;

    /**
     * Create a new scheduled task
     *
     * @param callable $callback
     * @param string $name
     * @param string $description
     * @param array $tags
     * @param string $timezone
     * @param string $expression
     */
    public function __construct(callable $callback, $name = '', $description = '', $tags = [], $timezone = 'UTC', $expression = '* * * * *')
    {
        $this->callback = $callback;
        $this->name = $name;
        $this->description = $description;
        $this->tags = $tags;
        $this->timezone = $timezone;
        $this->expression = $expression;
    }

    /**
     * Get the task name
     *
     * @return string
     */
    public function getName()
    {   
        return $this->name;
    }

    /**
     * Check if the task is due to run
     *
     * @return bool
     */
    public function isDue()
    {   
        if ($this->isEnabled) {
            $this->calculateNextRunTime();
            return $this->nextRunTime <= new \DateTime('now', new \DateTimeZone($this->timezone));
        }
        return false;
    }

    /**
     * Get the next run time
     *
     * @return \DateTime
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Run the scheduled task
     */
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

    /**
     * Calculate the next run time based on the cron expression
     *
     * @return \DateTime
     */
    public function calculateNextRunTime()
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
    
        // Extract cron fields
        list($minuteField, $hourField, $dayOfMonthField, $monthField, $dayOfWeekField) = $parts;
    
        // Initialize current time and timezone
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));
        $next = clone $now;
    
        // Only increment the minute by default, assuming the task is set to run every minute
        if ($minuteField === '*' && $hourField === '*' && $dayOfMonthField === '*' && $monthField === '*' && $dayOfWeekField === '*') {
            $next->modify('+1 minute');
            return $next;
        }
    
        // Calculate the next minute
        $nextMinute = $this->getNextFieldValue((int)$next->format('i'), $minuteField, 0, 59);
        if ($nextMinute < (int)$next->format('i')) {
            // Increment the hour if the calculated minute is in the past for the current hour
            $next->modify('+1 hour');
        }
        $next->setTime((int)$next->format('H'), $nextMinute);
    
        // Calculate the next hour
        $nextHour = $this->getNextFieldValue((int)$next->format('H'), $hourField, 0, 23);
        if ($nextHour < (int)$next->format('H')) {
            // Increment the day if the calculated hour is in the past for the current day
            $next->modify('+1 day');
        }
        $next->setTime($nextHour, (int)$next->format('i'));
    
        // Calculate the next day of the month
        $nextDay = $this->getNextFieldValue((int)$next->format('d'), $dayOfMonthField, 1, 31);
        if ($nextDay < (int)$next->format('d')) {
            // Increment the month if the calculated day is in the past for the current month
            $next->modify('+1 month');
        }
        $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $nextDay);
    
        // Calculate the next month
        $nextMonth = $this->getNextFieldValue((int)$next->format('n'), $monthField, 1, 12);
        if ($nextMonth < (int)$next->format('n')) {
            // Increment the year if the calculated month is in the past for the current year
            $next->modify('+1 year');
        }
        $next->setDate((int)$next->format('Y'), $nextMonth, (int)$next->format('d'));
    
        // Calculate the next day of the week if specified
        if ($dayOfWeekField !== '*') {
            $nextDayOfWeek = $this->getNextFieldValue((int)$next->format('w'), $dayOfWeekField, 0, 6);
            while ((int)$next->format('w') !== $nextDayOfWeek) {
                $next->modify('+1 day'); // Move forward by one day until it matches the specified day of the week
            }
        }
    
        return $next;
    }

    /**
     * Handle retries for the task
     *
     * @param \Exception $e
     */
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

    /**
     * Schedule the next run time for the task
     */
    private function scheduleNextRun()
    {
        // You can schedule the next run based on cron expression or your custom logic
        $this->nextRunTime = $this->calculateNextRunTime();
    }

    /**
     * Get the next valid field value
     *
     * @param int $currentValue
     * @param string $field
     * @param int $min
     * @param int $max
     * @return int
     */
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
