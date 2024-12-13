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
     * @return void
     */
    public function register(Application $application): void
    {
        $commandFiles = $this->getCommandFiles();

        foreach ($commandFiles as $file) {
            $namespace = $this->getNamespaceFromFilePath($file);
            $className = $namespace . '\\' . basename($file, '.php');

            try {
                $this->registerCommand($application, $className);
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
        return array_merge(
            glob(__DIR__ . '/../Core/Commands/*.php'),
            glob(__DIR__ . '/../Commands/*.php')
        );
    }

    /**
     * Get the namespace from the file path.
     *
     * @param string $filePath The file path.
     * @return string The namespace.
     */
    protected function getNamespaceFromFilePath(string $filePath): string
    {
        if (strpos($filePath, '/Core/Commands/') !== false) {
            return 'Services\Core\Commands';
        }
        return 'Services\Commands';
    }

    /**
     * Register a command with the application.
     *
     * This method checks if the given class Hm_CommandServiceProvider is a valid command class.
     * If it is valid, the command is instantiated and added to the application.
     * If not, the registration is simply skipped without any exceptions.
     *
     * @param Application $application The Symfony console application.
     * @param string $className The fully qualified name of the command class.
     * @return void
     */
    protected function registerCommand(Application $application, string $className): void
    {
        if (!class_exists($className) || !is_subclass_of($className, Hm_BaseCommand::class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" is not a valid command class.', $className));
        }

        $command = new $className;
        $application->add($command);
    }
}
