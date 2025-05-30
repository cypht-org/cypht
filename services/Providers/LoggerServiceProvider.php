<?php

namespace Services\Providers;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * LoggerServiceProvider is responsible for registering
 * the Monolog logger service in the dependency injection container.
 * It configures the logger to write to the standard output (console)
 * with a debug logging level.
 */
class LoggerServiceProvider
{
    /**
     * Register the logger service and its dependencies in the container.
     *
     * @param ContainerBuilder $container The DI container builder
     */
    public function register(ContainerBuilder $container): void
    {
        // Register a StreamHandler service that writes logs to "php://stdout"
        // and logs all messages at DEBUG level or higher.
        $container->register('logger.stream_handler', StreamHandler::class)
            ->addArgument('php://stdout')
            ->addArgument(Logger::DEBUG);

        // Register the main Logger service with the channel name 'app'.
        // Push the previously registered stream handler to it,
        // so the logger writes to stdout.
        $container->register(Logger::class, Logger::class)
            ->addArgument('cypht')
            ->addMethodCall('pushHandler', [new Reference('logger.stream_handler')]);

        // Set an alias so that any service requesting the PSR LoggerInterface
        // will receive the Monolog Logger service.
        $container->setAlias(LoggerInterface::class, Logger::class);
    }
}
