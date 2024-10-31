<?php

namespace Services\Commands;

use Psr\Container\ContainerInterface;
use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ListCommandsCommand is a console command that lists all registered commands in the application.
 */
class Hm_ListCommandsCommand extends Hm_BaseCommand
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

    protected function configure()
    {
        $this->setDescription('Lists all registered commands in the application.');
    }

    /**
     * Execute the command to list all registered commands.
     *
     * @param InputInterface $input The input interface Hm_ListCommandsCommand the command.
     * @param OutputInterface $output The output interface Hm_ListCommandsCommand the command.
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
