<?php

namespace Services\Core\Commands;

use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Services\Core\Hm_Container;
use Services\Core\Commands\Hm_BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Services\Core\Notifications\Hm_WebSocketServer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Hm_WebSocketServerCommand
 * @package Services\Core\Commands
 */
class Hm_WebSocketServerCommand extends Hm_BaseCommand
{
    /**
     * The name of the command.
     *
     * @var string
     */
    protected static $defaultName = 'websocket:server';

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setDescription('Start the WebSocket server to handle real-time notifications');
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
        $config = Hm_Container::getContainer()->get('config');
        $broadcastConfig = $config->get('broadcast');
        $output->writeln('Starting WebSocket server on '.$broadcastConfig['port'].'...');

        $server = IoServer::factory(
            new WsServer(
                new Hm_WebSocketServer()
            ),
            $broadcastConfig['port']
        );

        // Handle SIGINT (Ctrl+C) and SIGTERM (kill) to shut down gracefully
        pcntl_signal(SIGINT, function() use ($server, $output) {
            $output->writeln("Gracefully shutting down the WebSocket server...");
            exit;
        });

        $output->writeln('WebSocket server started on ws://localhost:'.$broadcastConfig['port']);

        // Run the WebSocket server
        $server->run();

        return Command::SUCCESS;
    }
}
