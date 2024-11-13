<?php

namespace Services\Core\Notifications\Channels;

use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use Services\Core\Hm_Container;

class Hm_BroadcastChannel extends Hm_NotificationChannel
{
    protected $wsClient;
    protected $connector;
    protected $connected = false;
    protected string $port;

    public function __construct()
    {
        $config = Hm_Container::getContainer()->get('config');
        $broadcastConfig = $config->get('broadcast');
        $this->port = $broadcastConfig['port'];
        $this->connector = new Connector();
        $this->connect();
    }

    /**
     * Attempt to connect to the WebSocket server.
     */
    protected function connect(): void
    {
        $this->connector('ws://localhost:'.$this->port)->then(function(WebSocket $conn) {
            $this->wsClient = $conn;
            $this->connected = true;

            $conn->on('message', function($msg) {
                echo "Received message: $msg\n";
            });

            $conn->on('close', function($code, $reason) {
                $this->connected = false;
                echo "Connection closed: $code, $reason\n";
                // TODO: Optionally, implement reconnect logic here
            });

        }, function(\Exception $e) {
            $this->connected = false;
            echo "Could not connect to WebSocket: {$e->getMessage()}\n";
            //TODO: Implement retry logic if needed
        });
    }

    /**
     * Send a notification via WebSocket.
     *
     * @param Hm_Notification $notification
     * @return void
     */
    public function send($notification): void
    {
        if ($this->connected && $this->wsClient) {
            // Prepare the message for the WebSocket
            $message = [
                'title' => $notification->getTitle(),
                'message' => $notification->getMessageText(),
                'recipient' => $notification->getRecipient(),
            ];

            // Send the message to the connected WebSocket client
            $this->wsClient->send(json_encode($message));

            echo "Message broadcasted via WebSocket!";
        } else {
            echo "WebSocket is not connected. Message not sent.\n";
        }
    }
}
