<?php

namespace Services\Core\Scheduling;

use Services\Traits\Hm_ScheduleFrequencyManager;

class ScheduledTask
{
    use Hm_ScheduleFrequencyManager;

    private $callback;
    private $nextRunTime;
    private $isEnabled = true;
    private $name;
    private $description;
    private $tags = [];
    private $lastRunTime;
    private $timezone;
    private $expression;

    public function __construct(callable $callback, $name = '', $description = '', $tags = [], $timezone = 'UTC', $expression = '* * * * *')
    {
        $this->callback = $callback;
        $this->name = $name;
        $this->description = $description;
        $this->tags = $tags;
        $this->timezone = $timezone;
        $this->expression = $expression;
        $this->nextRunTime = new \DateTime('now', new \DateTimeZone($this->timezone));
    }

    public function enable()
    {
        $this->isEnabled = true;
    }

    public function disable()
    {
        $this->isEnabled = false;
    }

    // Check if the task is due
    public function isDue()
    {
        return $this->isEnabled && new \DateTime('now', new \DateTimeZone($this->timezone)) >= $this->nextRunTime;
    }

    // Execute the task
    public function run()
    {
        if (!$this->isDue()) {
            return;
        }

        try {
            call_user_func($this->callback);
            $this->lastRunTime = new \DateTime('now', new \DateTimeZone($this->timezone));
        } catch (\Exception $e) {
            // Log the error message
            error_log("Error running task {$this->name}: " . $e->getMessage());
        }

        $this->scheduleNextRun();
    }

    // Schedule the next run time based on the cron expression
    public function scheduleNextRun()
    {
        $this->nextRunTime = $this->calculateNextRunTime();
    }

    public function getNextRunTime()
    {
        return $this->nextRunTime;
    }

    // Calculate the next run time based on the cron expression
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
            while ($next->format('w') !== $nextDayOfWeek) {
                $next->modify('+1 day');
            }
        } else {
            $next->modify('+1 week');
        }

        return $next;
    }

    // Get the next valid value for a cron field
    private function getNextFieldValue($currentValue, $field, $min, $max)
    {
        // Handle a field in cron syntax (minute, hour, day, month, weekday)
        $values = [];

        if ($field === '*') {
            return $min;
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
                    $values[] = $i;
                }
            } else {
                // Single values
                $values[] = (int)$part;
            }
        }

        // Filter and sort unique values
        $values = array_unique(array_filter($values, function ($value) use ($min, $max) {
            return $value >= $min && $value <= $max;
        }));

        sort($values);

        // Find the next valid value
        foreach ($values as $value) {
            if ($value > $currentValue) {
                return $value;
            }
        }

        return null; // If no valid next value is found
    }

    // Getter methods for the task properties
    public function getName() { return $this->name; }
    public function getDescription() { return $this->description; }
    public function getTags() { return $this->tags; }
    public function getTimezone() { return $this->timezone; }
}
