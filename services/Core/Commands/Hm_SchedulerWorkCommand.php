<?php

namespace Services\Core\Commands;

use Services\Core\Hm_Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Hm_SchedulerWorkCommand
 * @package Services\Core\Commands
 */
class Hm_SchedulerWorkCommand extends Hm_BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected static $defaultName = 'schedule:work';

    /**
     * Flag to indicate if the scheduler should stop running.
     * @var bool
     */
    private $shouldStop = false;

    /**
     * Store the last run time for each task to prevent overlapping runs.
     * @var array
     */
    private $lastRunTimes = [];

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setDescription('Continuously run the scheduler to execute due tasks')
            ->setHelp('This command runs the scheduler in a loop to continuously check and execute scheduled tasks.');
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
        $output->writeln("Scheduler started. Press Ctrl+C to stop.");

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->shouldStop = true;
            });
        }

        while (!$this->shouldStop) {
            foreach ($scheduler->getTasks() as $task) {
                $taskId = spl_object_hash($task);
                $currentTime = new \DateTime('now', new \DateTimeZone($task->getTimezone()));

                $lastRunTime = isset($this->lastRunTimes[$taskId]) ? $this->lastRunTimes[$taskId] : $currentTime;

                $this->lastRunTimes[$taskId] = $currentTime;

                if ($task->isDue() && $currentTime > $lastRunTime) {
                    $output->writeln("Running task: {$task->getName()} at " . $currentTime->format('Y-m-d H:i:s'));
                    $task->run();
                    $output->writeln("Task: {$task->getName()} added to queue");
                }
            }

            // Wait one minute before the next loop iteration
            sleep(60);

            // Dispatch any pending signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $output->writeln("Scheduler stopped gracefully.");

        return Command::SUCCESS;
    }

    /**
     * Stops the scheduler loop gracefully.
     */
    public function stop()
    {
        $this->shouldStop = true;
    }
}
