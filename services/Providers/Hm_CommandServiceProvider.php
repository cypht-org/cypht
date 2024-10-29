<?php

namespace Services\Providers;

use Hm_Debug;
use Psr\Container\ContainerInterface;
use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;

class Hm_CommandServiceProvider
{
    /**
     * Register commands in the application.
     *
     * @param Application $application The Symfony console application.
     * @param ContainerInterface $container The dependency injection container.
     * @return void
     */
    public function register(Application $application, ContainerInterface $container): void
    {
        $commandNamespace = 'Services\Commands';
        $commandFiles = $this->getCommandFiles();

        foreach ($commandFiles as $file) {
            $className = $commandNamespace . '\\' . basename($file, '.php');
            try {
                $this->registerCommand($application, $className, $container);
            } catch (InvalidArgumentException $e) {
                Hm_Debug::add(sprintf('Command registration escaped for %s: No need to register the command class.', $className));
            }
        }
    }

    /**
     * Get the command files from the commands directory.
     *
     * @return array List of command files.
     */
    protected function getCommandFiles(): array
    {
        return glob(__DIR__ . '/../Commands/*.php');
    }

    /**
     * Register a command with the application.
     *
     * This method checks if the given class name is a valid command class.
     * If it is valid, the command is instantiated and added to the application.
     * If not, the registration is simply skipped without any exceptions.
     *
     * @param Application $application The Symfony console application.
     * @param string $className The fully qualified name of the command class.
     * @param ContainerInterface $container The dependency injection container.
     * @return void
     */
    protected function registerCommand(Application $application, string $className, ContainerInterface $container): void
    {
        if (!class_exists($className) || !is_subclass_of($className, Hm_BaseCommand::class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" is not a valid command class.', $className));
        }

        $command = new $className($container);
        $application->add($command);
    }
}
