<?php

namespace Services\Core\Scheduling;

use Hm_Cache;
use Hm_Debug;
use Hm_Msgs;
use Hm_Session_Setup;

class Hm_Scheduler
{
    protected $tasks = [];
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function command($command)
    {
        $cache = new Hm_Cache($this->config, new Hm_Session_Setup($this->config));
        $commandTask = new Hm_CommandTask($command, new Hm_CacheMutex($cache));
        $this->tasks[] = $commandTask;
        return $commandTask;
    }

    /**
     * Register a new task with the given configuration.
     *
     * @param callable $callback
     * @param string $name
     * @param string $description
     * @param array $tags
     * @param string $timezone
     * @return ScheduledTask
     */
    public function register(callable $callback, $name = '', $description = '', $tags = [], $timezone = 'UTC')
    {
        $task = new Hm_ScheduledTask($callback, $name, $description, $tags, $timezone);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Run all due tasks.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->tasks as $task) {
            if ($task->isDue()) {
                try {
                    $task->run();
                    Hm_Debug::add("Task '{$task->getName()}' executed successfully.");
                    Hm_Msgs::add("Task '{$task->getName()}' executed successfully.");
                } catch (\Exception $e) {
                    Hm_Debug::add("ERRORExecuting task '{$task->getName()}': " . $e->getMessage());
                    Hm_Msgs::add("ERRORExecuting task '{$task->getName()}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Display all scheduled tasks (for debugging).
     *
     * @return void
     */
    public function displayScheduledTasks()
    {
        foreach ($this->tasks as $task) {
            echo "Task Name: {$task->name}, Next Run Time: {$task->nextRunTime->format('Y-m-d H:i:s')}, Last Run Time: " . ($task->lastRunTime ? $task->lastRunTime->format('Y-m-d H:i:s') : 'Never') . "\n";
        }
    }
}
