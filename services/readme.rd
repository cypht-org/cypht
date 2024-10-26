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