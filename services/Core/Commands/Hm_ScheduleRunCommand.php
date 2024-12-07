<?php

namespace Services\Core\Commands;

use Services\Core\Hm_Container;
use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Hm_ScheduleRunCommand
 * @package Services\Core\Commands
 */
class Hm_ScheduleRunCommand extends Hm_BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected static $defaultName = 'schedule:run';

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setDescription('Run all scheduled tasks that are due')
            // Optionally, you can add other configuration or arguments here
            ;
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scheduler = Hm_Container::getContainer()->get('scheduler');
        $scheduler->run();
        
        $output->writeln("All due scheduled tasks have been executed.");

        return Command::SUCCESS;
    }
}
