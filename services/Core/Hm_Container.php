<?php

namespace Services\Core;

use Hm_DB;
use Hm_Redis;
use Hm_AmazonSQS;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Services\Providers\{ Hm_CommandServiceProvider, Hm_EventServiceProvider, Hm_SchedulerServiceProvider, Hm_QueueServiceProvider };

class Hm_Container
{
    private static $container = null;

    // Prevent direct instantiation and cloning
    private function __construct() {}
    private function __clone() {}

    public static function setContainer(ContainerBuilder $containerBuilder): ContainerBuilder
    {
        if (self::$container === null) {
            self::$container = $containerBuilder; 
        }

        return self::$container;
    }

    public static function bind(): ContainerBuilder
    {
        // Register Hm_DB
        self::$container->set('db.connection', Hm_DB::connect(self::$container->get('config')));

        self::$container->register('db', Hm_DB::class)
        ->setShared(true);

        // Register Hm_Redis
        $redis = new Hm_Redis(self::$container->get('config'));
        $redis->connect();
        self::$container->set('redis.connection', $redis->getInstance());
        self::$container->register('redis', Hm_Redis::class)->setArgument(0, self::$container->get('config'))

        ->setShared(true);

        // Register Hm_AmazonSQS
        self::$container->set('amazon.sqs.connection', Hm_AmazonSQS::connect(self::$container->get('config')));
        self::$container->register('amazon.sqs',Hm_AmazonSQS::class)
        ->setShared(true);

        // Register Hm_CommandServiceProvider
        self::$container->register('command.serviceProvider', Hm_CommandServiceProvider::class)
        ->setShared(true);

        // Register Hm_QueueServiceProvider
        self::$container->register('queue.ServiceProvider',Hm_QueueServiceProvider::class)
        ->setShared(true);

        self::$container->register('scheduler.ServiceProvider', Hm_SchedulerServiceProvider::class)
        ->setShared(true);
        self::$container->register('event.ServiceProvider', Hm_EventServiceProvider::class)
        ->setShared(true);

        return self::$container;
    }

    public static function getContainer(): ContainerBuilder
    {
        return self::$container;
    }
}
