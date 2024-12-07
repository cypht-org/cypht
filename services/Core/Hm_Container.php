<?php

namespace Services\Core;

use Hm_DB;
use Hm_Redis;
use Hm_AmazonSQS;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Services\Providers\{Hm_CommandServiceProvider, Hm_EventServiceProvider, Hm_SchedulerServiceProvider, Hm_QueueServiceProvider};

/**
 * Class Hm_Container
 * @package Services\Core
 */
class Hm_Container
{
    private static $container = null;

    // Prevent direct instantiation and cloning
    private function __construct() {}
    private function __clone() {}

    /**
     * Set the container
     *
     * @param ContainerBuilder $containerBuilder
     * @return ContainerBuilder
     */
    public static function setContainer(ContainerBuilder $containerBuilder): ContainerBuilder
    {
        if (self::$container === null) {
            self::$container = $containerBuilder;
        }

        return self::$container;
    }

    /**
     * Bind the container
     *
     * @return ContainerBuilder
     */
    public static function bind(): ContainerBuilder
    {
        $config = self::$container->get('config');

        if ($config->get('queue_enabled')) {
            
            if ($config->get('queue_driver') === 'database') {
                // Register Hm_DB
                self::$container->set('db.connection', Hm_DB::connect(self::$container->get('config')));
        
                self::$container->register('db', Hm_DB::class)->setPublic(true);
            } else if ($config->get('queue_driver') === 'redis') {
                // Register Hm_Redis
                $redis = new Hm_Redis($config);
                $redis->connect();
                self::$container->set('redis.connection', $redis->getInstance());
                self::$container->register('redis', Hm_Redis::class)->setArgument(0, self::$container->get('config'))->setPublic(true);
            } else if ($config->get('queue_enabled') && $config->get('queue_driver') === 'sqs') {
                // Register Hm_AmazonSQS
                self::$container->set('amazon.sqs.connection', Hm_AmazonSQS::connect(self::$container->get('config')));
                self::$container->register('amazon.sqs', Hm_AmazonSQS::class)
                    ->setPublic(true);
            }
        }

        // Register Hm_CommandServiceProvider
        self::$container->register('command.serviceProvider', Hm_CommandServiceProvider::class)
            ->setPublic(true);

        // Register Hm_QueueServiceProvider
        self::$container->register('queue.ServiceProvider', Hm_QueueServiceProvider::class)
            ->setPublic(true);

        self::$container->register('scheduler.ServiceProvider', Hm_SchedulerServiceProvider::class)
            ->setPublic(true);

        self::$container->register('event.ServiceProvider', Hm_EventServiceProvider::class)
            ->setPublic(true);

        return self::$container;
    }

    /**
     * Get the container
     *
     * @return ContainerBuilder
     */
    public static function getContainer(): ContainerBuilder
    {
        return self::$container;
    }
}
