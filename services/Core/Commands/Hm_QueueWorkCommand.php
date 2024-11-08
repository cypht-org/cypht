<?php

namespace Services\Core\Commands;

use Services\Core\Commands\Hm_BaseCommand;
use Services\Core\Hm_Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Hm_QueueWorkCommand extends Hm_BaseCommand
{
    protected static $defaultName = 'queue:work';

    protected function configure()
    {
        $this
            ->setDescription('Start processing jobs on the queue')
            ->addArgument('connection', InputArgument::OPTIONAL, 'The name of the queue connection to work')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the worker', 'default')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The names of the queues to work')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Only process the next job on the queue')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3)
            ->addOption('tries', null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $input->getArgument('connection') ?: env('QUEUE_DRIVER', 'database');
        $queue = $input->getOption('queue') ?: 'default';

        $output->writeln("Processing jobs from the [$queue] on connection [$connection]...");

        if ($input->getOption('once')) {
            Hm_Container::getContainer()->get('queue.worker')->work();
        } else {
            while (true) {
                Hm_Container::getContainer()->get('queue.worker')->work();
                sleep($input->getOption('sleep'));
            }
        }

        return Command::SUCCESS;
    }
}
