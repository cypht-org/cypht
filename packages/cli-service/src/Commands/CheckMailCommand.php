<?php

namespace Cypht\Service\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckMailCommand extends BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected static $defaultName = 'check:mail';


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

        if (!empty($newMessages)) {
            $this->success('You have new messages!');
            // dispatch event
        } else {
            $this->info('No new messages.');
        }

        return Command::SUCCESS;
    }
}