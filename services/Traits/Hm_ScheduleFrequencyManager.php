<?php

namespace Services\Traits;

trait Hm_ScheduleFrequencyManager
{
    /**
     * The expression representing the event's frequency.
     *
     * @var string
     */
    protected $expression = '* * * * *'; // Default to every minute

    /**
     * The timezone for scheduling.
     *
     * @var string
     */
    protected $timezone = 'UTC';

    /**
     * Set the Cron expression for the event's frequency.
     *
     * @param  string  $expression
     * @return $this
     */
    public function cron($expression)
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Schedule the event to run between start and end time.
     *
     * @param  string  $startTime
     * @param  string  $endTime
     * @return $this
     */
    public function between($startTime, $endTime)
    {
        return $this->when($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * Schedule the event to not run between start and end time.
     *
     * @param  string  $startTime
     * @param  string  $endTime
     * @return $this
     */
    public function unlessBetween($startTime, $endTime)
    {
        return $this->skip($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * Check if the current time is within the given time interval.
     *
     * @param  string  $startTime
     * @param  string  $endTime
     * @return \Closure
     */
    private function inTimeInterval($startTime, $endTime)
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));
        $startTime = new \DateTime($startTime, new \DateTimeZone($this->timezone));
        $endTime = new \DateTime($endTime, new \DateTimeZone($this->timezone));

        // Adjust for overnight intervals
        if ($endTime < $startTime) {
            if ($startTime > $now) {
                $startTime->modify('-1 day');
            } else {
                $endTime->modify('+1 day');
            }
        }

        return function () use ($now, $startTime, $endTime) {
            return $now >= $startTime && $now <= $endTime;
        };
    }

    /**
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->cron('* * * * *');
    }

    /**
     * Schedule the event to run every two minutes.
     *
     * @return $this
     */
    public function everyTwoMinutes()
    {
        return $this->cron('*/2 * * * *');
    }

    /**
     * Schedule the event to run every three minutes.
     *
     * @return $this
     */
    public function everyThreeMinutes()
    {
        return $this->cron('*/3 * * * *');
    }

    /**
     * Schedule the event to run every four minutes.
     *
     * @return $this
     */
    public function everyFourMinutes()
    {
        return $this->cron('*/4 * * * *');
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Schedule the event to run every fifteen minutes.
     *
     * @return $this
     */
    public function everyFifteenMinutes()
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Schedule the event to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->cron('0,30 * * * *');
    }

    /**
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Schedule the event to run hourly at a given offset in the hour.
     *
     * @param  array|int  $offset
     * @return $this
     */
    public function hourlyAt($offset)
    {
        $offset = is_array($offset) ? implode(',', $offset) : $offset;
        return $this->cron("$offset * * * *");
    }

    /**
     * Schedule the event to run daily.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->cron('0 0 * * *'); // Midnight
    }

    /**
     * Schedule the event to run daily at a given time (10:00, 19:30, etc).
     *
     * @param  string  $time
     * @return $this
     */
    public function dailyAt($time)
    {
        $segments = explode(':', $time);
        return $this->cron(count($segments) === 2 ? "{$segments[1]} {$segments[0]} * * *" : "0 {$segments[0]} * * *");
    }

    /**
     * Set the timezone for scheduling.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Splice the given value into the given position of the expression.
     *
     * @param  int  $position
     * @param  string  $value
     * @return $this
     */
    protected function spliceIntoPosition($position, $value)
    {
        $segments = preg_split("/\s+/", $this->expression);
        $segments[$position - 1] = $value;
        return $this->cron(implode(' ', $segments));
    }

    /**
     * Skip task execution based on a condition.
     * 
     * @param callable $condition
     * @return $this
     */
    protected function skip(callable $condition)
    {
        if ($condition()) {
            echo "Skipping task due to the condition.\n";
            return $this;
        }
        return $this;
    }
}
