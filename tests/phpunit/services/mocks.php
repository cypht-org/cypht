<?php

use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TestCommand is a mock command for testing BaseCommand functionalities.
 */
class Hm_TestCommand extends Hm_BaseCommand
{
    /**
     * The name of the command (e.g., "test:command").
     * @var string
     */
    protected $commandName = 'test:command';

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->success("Test command executed successfully.");
        return Command::SUCCESS;
    }

    /**
     * Override the protected success method for testing.
     *
     * @param string $message
     */
    public function success(string $message): void
    {
        parent::success($message);
    }

    /**
     * Override the protected success method for testing.
     *
     * @param string $message
     */
    public function error(string $message): void
    {
        parent::error($message);
    }

    /**
     * Override the protected success method for testing.
     *
     * @param string $message
     */
    public function info(string $message): void
    {
        parent::info($message);
    }
    /**
     * Override the protected success method for testing.
     *
     * @param string $message
     */
    public function warning(string $message): void
    {
        parent::warning($message);
    }
    /**
     * Override the protected success method for testing.
     *
     * @param string $message
     */
    public function text(string $message): void
    {
        parent::text($message);
    }

    public function getService(string $message)
    {
        parent::getService($message);
    }
}