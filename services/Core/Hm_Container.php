<?php

namespace Services\Core;

use Hm_DB;
use Hm_Redis;
use Hm_AmazonSQS;
use Hm_Site_Config_File;
use Services\Core\Queue\Hm_QueueManager;
use Services\Core\Queue\Hm_JobDispatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Services\Providers\{ Hm_CommandServiceProvider, Hm_EventServiceProvider, Hm_SchedulerServiceProvider, Hm_QueueServiceProvider };


class Hm_Container
{
    private static $container = null;

    // Prevent direct instantiation and cloning
    private function __construct() {}
    private function __clone() {}

    public static function getContainer(): ContainerBuilder
    {
        if (self::$container === null) {
            self::$container = new ContainerBuilder();
            // Register Hm_DB
            self::$container->register('db', Hm_DB::class)
            ->setShared(true);

            // Register Hm_Redis
            self::$container->register('redis', Hm_Redis::class)
            ->setShared(true);

            // Register Hm_AmazonSQS
            self::$container->register('amazon.sqs', Hm_AmazonSQS::class)
            ->setShared(true);

            // Register Hm_QueueManager
            self::$container->register('queue.manager', Hm_QueueManager::class)
            ->setShared(true);

            // Register Hm_JobDispatcher
            self::$container->register('job.dispatcher', Hm_JobDispatcher::class)
            ->setShared(true);

            // Register Hm_Site_Config_File
            self::$container->register('Hm_Site_Config_File', Hm_Site_Config_File::class)
            ->setShared(true);

            // Register Hm_CommandServiceProvider
            self::$container->register('command.serviceProvider', Hm_CommandServiceProvider::class)
            ->setShared(true);

            // Register Hm_QueueServiceProvider
            self::$container->register('queue.ServiceProvider',Hm_QueueServiceProvider::class)
            // ->addArgument(new \Symfony\Component\DependencyInjection\Reference(Hm_Site_Config_File::class))
            // ->addArgument(null)
            ->setShared(true);

            self::$container->register('scheduler.ServiceProvider', Hm_SchedulerServiceProvider::class)
            ->setShared(true);
            self::$container->register('event.ServiceProvider', Hm_EventServiceProvider::class)
            ->setShared(true);
        }
        return self::$container;
    }
}
