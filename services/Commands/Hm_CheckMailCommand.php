<?php

namespace Services\Commands;

use Services\Jobs\Hm_ProcessNewEmail;
use Services\Core\Commands\Hm_BaseCommand;
use Services\Notifications\Hm_NewMailNotification;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Hm_CheckMailCommand extends Hm_BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected static $defaultName = 'check:mail';

    protected function configure()
    {
        $this->setDescription('Check for new mail');
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
        $this->info("Checking for new mail...");
        $message = "Hello! You have a new mail on test@entreprise server.";

        $notification = new Hm_NewMailNotification($message);
        $notification->sendNow();
        // Hm_NewMailNotification::dispatch($message);
        // Hm_ProcessNewEmail::dispatch(email: 'muhngesteven@gmail.com');
        return Command::SUCCESS;
    }
}
