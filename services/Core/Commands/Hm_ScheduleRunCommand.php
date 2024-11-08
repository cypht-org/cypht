<?php

namespace Services\Core\Commands;

use Services\Core\Hm_Container;
use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Hm_ScheduleRunCommand extends Hm_BaseCommand
{
    // Default name for the command
    protected static $defaultName = 'schedule:run';

    protected function configure()
    {
        $this
            ->setDescription('Run all scheduled tasks that are due')
            // Optionally, you can add other configuration or arguments here
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get the scheduler instance from the container
        $scheduler = Hm_Container::getContainer()->get('scheduler');
        // Run the tasks that are due
        $scheduler->run();
        
        $output->writeln("All due scheduled tasks have been executed.");

        return Command::SUCCESS;
    }
}
