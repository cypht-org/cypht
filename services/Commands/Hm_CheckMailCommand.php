<?php

namespace Services\Commands;

use Services\Jobs\Hm_ProcessNewEmail;
use Services\Core\Commands\Hm_BaseCommand;
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

        // Example: Call the mail checking service from the container
        // $imap = $this->getService('Hm_Imap');
        // $newMessages = $imap->search('UNSEEN'); 
        (new Hm_ProcessNewEmail('muhngesteven@gmail.com'))->handle();

        if (!empty($newMessages)) {
            $this->success('You have new messages!');
            // dispatch event
        } else {
            $this->info('No new messages.');
        }

        return Command::SUCCESS;
    }
}
