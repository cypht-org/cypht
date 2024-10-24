<?php

namespace Cypht\Service\Commands;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ListCommandsCommand is a console command that lists all registered commands in the application.
 */
class ListCommandsCommand extends BaseCommand
{
    protected static $defaultName = 'list';

    /**
     * ListCommandsCommand constructor.
     *
     * @param ContainerInterface $container Dependency Injection Container.
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * Execute the command to list all registered commands.
     *
     * @param InputInterface $input The input interface for the command.
     * @param OutputInterface $output The output interface for the command.
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->success('Registered Commands:');

        $this->initialize($input, $output);

        $commands = $this->getApplication()->all();

        $commandList = [];

        foreach ($commands as $command) {
            $commandList[] = sprintf('%-30s - %s', $command->getName(), $command->getDescription());
        }

        $this->text(implode(PHP_EOL, $commandList));

        return Command::SUCCESS;
    }
}
