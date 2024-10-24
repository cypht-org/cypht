<?php

namespace Cypht\Service\Commands;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * BaseCommand provides common functionality for all console commands.
 */
abstract class BaseCommand extends Command
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * BaseCommand constructor.
     *
     * @param ContainerInterface $container Dependency Injection Container.
     * @param string|null $name The name of the command.
     */
    public function __construct(ContainerInterface $container, ?string $name = null)
    {
        parent::__construct($name);

        $this->container = $container;
    }

    /**
     * Initialize SymfonyStyle for consistent command output formatting.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Execute the console command.
     * 
     * This method can be overridden in subclasses to provide specific command functionality.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info('Executing command: ' . $this->getName());

        return Command::SUCCESS;
    }
    /**
     * Get a service from the container.
     *
     * @param string $service The service class or interface.
     * @return mixed The requested service.
     */
    protected function getService(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * Output a success message.
     *
     * @param string $message
     */
    protected function success(string $message): void
    {
        $this->io->success($message);
    }

    /**
     * Output an error message.
     *
     * @param string $message
     */
    protected function error(string $message): void
    {
        $this->io->error($message);
    }

    /**
     * Output an informational message.
     *
     * @param string $message
     */
    protected function info(string $message): void
    {
        $this->io->info($message);
    }

    /**
     * Output a warning message.
     *
     * @param string $message
     */
    protected function warning(string $message): void
    {
        $this->io->warning($message);
    }

    /**
     * Output a simple text message.
     *
     * @param string $message
     */
    protected function text(string $message): void
    {
        $this->io->text($message);
    }
}
