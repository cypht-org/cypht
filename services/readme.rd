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

#Adding scheduler
you can use register or command on `$scheduler`, we prepared the class `Hm_Kernel` for that:
```
<?php
// Register a task with custom name, description, tags, and timezone
$scheduler->register(function () {
    echo "Running database cleanup\n";
}, 'cleanup', 'Daily database cleanup task', ['database', 'daily'], 'America/New_York')->dailyAt('03:00');

// Register command tasks with options
$scheduler->command('check:mail')->everyMinute()
    ->withoutOverlapping(10);

$scheduler->command('backup:database')->dailyAt('02:00');
```

as we now have `Hm_SchedulerRunCommand.php` we can do:
```
* * * * * cd /path-to-your-project && php console schedule:run >> /dev/null 2>&1
```
#Running the Scheduler Locally

Typically, you would not add a scheduler cron entry to your local development machine. Instead, you may use the `schedule:work` Console command. This command will run in the foreground and invoke the scheduler every minute until you terminate the command:
```
php console schedule:work
```

#Dispatch the event
``` 
(new NewEmailProcessedEvent)->dispatch('user@example.com');
```

#Notification Example usage
```
use Services\Notifications\UserNotification;

// Configure the notification channels
$config = [
    'channels' => ['slack', 'telegram'], // User-defined channels
];

// Create an instance of UserNotification with the specified channels
$notification = new UserNotification($config);

// Send a message through the specified channels
$message = "Hello! You have a new alert.";
$notification->sendNotification($message);
```

#Database queue tables

We have twi database that we using to manage database queue: `hm_jobs` & `hm_failed_jobs`
```
-- Active jobs table
CREATE TABLE hm_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payload TEXT,
    attempts INT DEFAULT 0
);

-- Failed jobs table
CREATE TABLE hm_failed_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payload TEXT,
    failed_at DATETIME,
    exception TEXT
);
```