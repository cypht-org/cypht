<?php
namespace Services\Providers;

use Services\Core\Queue\Hm_QueueWorker;
use Services\Core\Queue\Hm_QueueManager;
use Services\Core\Queue\Hm_JobDispatcher;
use Services\Core\Queue\Drivers\Hm_RedisQueue;
use Services\Core\Queue\Drivers\Hm_DatabaseQueue;
use Services\Core\Queue\Drivers\Hm_AmazonSQSQueue;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Hm_QueueServiceProvider
{
    protected Hm_QueueManager $queueManager;

    public function register(ContainerBuilder $containerBuilder): void
    {
        $queueConnection = getenv('QUEUE_CONNECTION') ?: 'database';

        switch ($queueConnection) {
            case 'redis':
                $containerBuilder->register('queue.driver.redis', Hm_RedisQueue::class)
                    ->addArgument(new Reference('redis'))
                    ->addArgument('default');
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['redis', new Reference('queue.driver.redis')]);
                break;
            case 'sqs':
                $containerBuilder->register('queue.driver.database', Hm_AmazonSQSQueue::class)
                    ->addArgument($containerBuilder->get('amazon.sqs'));
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['sqs', new Reference('queue.driver.redis')]);
                break;
            default:
                $containerBuilder->register('queue.driver.database', Hm_DatabaseQueue::class)
                    ->addArgument($containerBuilder->get('db'));
                $containerBuilder->getDefinition('queue.manager')
                    ->addMethodCall('addDriver', ['database', new Reference('queue.driver.database')]);
                break;
        }
        $containerBuilder->register('job.dispatcher', Hm_JobDispatcher::class)
            ->addArgument(new Reference('queue.manager'))
            ->setShared(true);

        $containerBuilder->register('queue.worker', Hm_QueueWorker::class)
            ->addArgument(new Reference('queue.driver.' . $queueConnection))
            ->setShared(true);
    }
}
