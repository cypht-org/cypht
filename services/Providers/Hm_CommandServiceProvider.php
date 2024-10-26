<?php

namespace Services\Providers;

use Psr\Container\ContainerInterface;
use Services\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Application;

class Hm_CommandServiceProvider
{
    /**
     * Register commands in the application.
     *
     * @param Application $application The Symfony console application.
     * @param ContainerInterface $container The dependency injection container.
     */
    public function register(Application $application, ContainerInterface $container): void
    {
        $commandNamespace = 'Services\Commands';
        $commandFiles = glob(__DIR__ . '/../Commands/*.php');

        foreach ($commandFiles as $file) {
            $className = $commandNamespace . '\\' . basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, Hm_BaseCommand::class)) {
                $command = new $className($container);
                $application->add($command);
            }
        }
    }
}
