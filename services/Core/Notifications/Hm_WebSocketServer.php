<?php

namespace Services\Core\Notifications;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class Hm_WebSocketServer implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    // Called when a client connects
    public function onOpen(ConnectionInterface $conn)
    {
        echo "New connection: ({$conn->resourceId})\n";
        $this->clients->attach($conn);
    }

    // Called when a message is received from a client
    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Message from ({$from->resourceId}): $msg\n";

        // Broadcast the message to all connected clients
        foreach ($this->clients as $client) {
            // Don't send the message back to the sender
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }

    // Called when a client disconnects
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    // Called on error
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
