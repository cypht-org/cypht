<?php
namespace Services\Providers;

use Services\Core\Hm_Container;
use Services\Core\Queue\Hm_QueueWorker;
use Services\Core\Queue\Hm_QueueManager;
use Services\Core\Jobs\Hm_JobDispatcher;
use Services\Core\Queue\Drivers\Hm_RedisQueue;
use Services\Core\Queue\Drivers\Hm_DatabaseQueue;
use Services\Core\Queue\Drivers\Hm_AmazonSQSQueue;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Hm_QueueServiceProvider
 * @package Services\Providers
 */
class Hm_QueueServiceProvider
{
    /**
     * @var Hm_QueueManager
     */
    protected Hm_QueueManager $queueManager;

    /**
     * Register the service provider
     */
    public function register()
    {
        $containerBuilder = Hm_Container::getContainer();
        $config = $containerBuilder->get('config');
        $queueConnection = $config->get('queue_driver');
        
        $containerBuilder->register('queue.manager', Hm_QueueManager::class)
        ->setShared(true);

        $containerBuilder->register('job.dispatcher', Hm_JobDispatcher::class)
        ->addArgument(new Reference('queue.manager'))
        ->setShared(true);

        $containerBuilder->register('queue.worker', Hm_QueueWorker::class)
            ->addArgument(new Reference('queue.driver.' . $queueConnection))
            ->setShared(true);

        switch ($queueConnection) {
            case 'redis':
                $containerBuilder->register('queue.driver.redis', Hm_RedisQueue::class)
                    ->addArgument(new Reference('redis'))
                    ->addArgument(new Reference('redis.connection'))
                    ->addArgument('default');
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['redis', new Reference('queue.driver.redis')]);
                break;
            case 'sqs':
                $containerBuilder->register('queue.driver.sqs', Hm_AmazonSQSQueue::class)
                    ->addArgument(new Reference('amazon.sqs'))
                    ->addArgument(new Reference('amazon.sqs.connection'));
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['sqs', new Reference('queue.driver.sqs')]);
                break;
            case 'database':
                $containerBuilder->register('queue.driver.database', Hm_DatabaseQueue::class)
                    ->addArgument(new Reference('db'))
                    ->addArgument(new Reference('db.connection'));
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['database', new Reference('queue.driver.database')]);
                break;
        }
    }
}
