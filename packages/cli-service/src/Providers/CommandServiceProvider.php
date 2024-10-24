<?php

namespace Cypht\Service\Providers;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Cypht\Service\Commands\BaseCommand;

class CommandServiceProvider
{
    /**
     * Register commands in the application.
     *
     * @param Application $application The Symfony console application.
     * @param ContainerInterface $container The dependency injection container.
     */
    public function register(Application $application, ContainerInterface $container): void
    {
        $commandNamespace = 'Cypht\Service\Commands';
        $commandFiles = glob(__DIR__ . '/../Commands/*.php');

        foreach ($commandFiles as $file) {
            $className = $commandNamespace . '\\' . basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, BaseCommand::class)) {
                $command = new $className($container);
                $application->add($command);
            }
        }
    }
}
