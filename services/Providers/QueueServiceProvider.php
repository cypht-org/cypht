<?php
namespace Services\Providers;

use Services\Core\Container;
use Services\Core\Queue\QueueWorker;
use Services\Core\Queue\QueueManager;
use Services\Core\Jobs\JobDispatcher;
use Services\Core\Queue\Drivers\RedisQueue;
use Services\Core\Queue\Drivers\DatabaseQueue;
use Services\Core\Queue\Drivers\AmazonSQSQueue;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Hm_QueueServiceProvider
 * @package Services\Providers
 */
class QueueServiceProvider
{
    /**
     * @var Hm_QueueManager
     */
    protected QueueManager $queueManager;

    /**
     * Register the service provider
     */
    public function register()
    {
        $containerBuilder = Container::getContainer();
        $config = $containerBuilder->get('config');
        $queueConnection = $config->get('queue_driver');
        
        $containerBuilder->register('queue.manager', QueueManager::class)
        ->setPublic(true);

        $containerBuilder->register('job.dispatcher', JobDispatcher::class)
        ->addArgument(new Reference('queue.manager'))
        ->setPublic(true);

        $containerBuilder->register('queue.worker', QueueWorker::class)
            ->addArgument(new Reference('queue.driver.' . $queueConnection))
            ->setPublic(true);

        switch ($queueConnection) {
            case 'redis':
                $containerBuilder->register('queue.driver.redis', RedisQueue::class)
                    ->addArgument(new Reference('redis'))
                    ->addArgument(new Reference('redis.connection'))
                    ->addArgument('default')
                    ->setPublic(true);
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['redis', new Reference('queue.driver.redis')]);
                break;
            case 'sqs':
                $containerBuilder->register('queue.driver.sqs', AmazonSQSQueue::class)
                    ->addArgument(new Reference('amazon.sqs'))
                    ->addArgument(new Reference('amazon.sqs.connection'))
                    ->setPublic(true);
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['sqs', new Reference('queue.driver.sqs')]);
                break;
            case 'database':
                $containerBuilder->register('queue.driver.database', DatabaseQueue::class)
                    ->addArgument(new Reference('db'))
                    ->addArgument(new Reference('db.connection'))
                    ->setPublic(true);
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['database', new Reference('queue.driver.database')]);
                break;
        }
    }
}
