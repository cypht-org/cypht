#Queue usage example:

```
// Setup queue manager and drivers
$queueManager = new QueueManager();
$queueManager->addDriver('redis', new RedisQueue(new Redis()));
$queueManager->addDriver('database', new DatabaseQueue(new PDO('mysql:dbname=testdb;host=127.0.0.1', 'user', 'pass')));

// Initialize dispatcher and dispatch a job
$dispatcher = new JobDispatcher($queueManager);
$dispatcher->dispatch(new ProcessNewEmail('example@example.com'));

// Initialize worker to process jobs from the queue
$worker = new QueueWorker($queueManager->getDriver('redis'));
$worker->work();
```

```
<?php

require 'path/to/your/autoload.php';

use Services\Scheduling\Scheduler;

// Initialize the scheduler
$scheduler = new Scheduler($config);


// Register a task with custom name, description, tags, and timezone
$scheduler->register(function () {
    echo "Running database cleanup\n";
}, 'cleanup', 'Daily database cleanup task', ['database', 'daily'], 'America/New_York')->dailyAt('03:00');

// Register command tasks with options
$scheduler->command('check:mail')->everyMinute()
    ->withoutOverlapping(10);

$scheduler->command('backup:database')->dailyAt('02:00');
```

```
// Dispatch the event
(new NewEmailProcessedEvent)->dispatch('user@example.com');
```