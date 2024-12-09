<?php

namespace Services\Core\Notifications;

use Services\Traits\Hm_Dispatchable;
use Services\Core\Queue\Hm_Queueable;
use Services\Contracts\Notifications\Hm_Dispatcher;

/**
 * Notification class
 * package: Services\Core\Notifications
 */
class Hm_Notification extends Hm_Queueable implements Hm_Dispatcher
{
    use Hm_Dispatchable;

      /**
     * The notification content(message).
     * 
     * @var string
     */
    public string $content = '';

    /**
     * Constructor.
     * 
     * @param string $content The notification content.
     */
    public function __construct($content = '')
    {
        $this->content = $content;
        $this->driver = env('QUEUE_DRIVER');
    }
    /**
     * Notifcations can be sent through multiple channels.
     * 
     * @return array
     */
    public function via(): array
    {
        return [];
    }

    public function handle(): void
    {
        dump("Processing ".self::class);

        $this->sendNow();
    }
    public function failed(): void
    {
        echo "Notification failed to send!";
    }

    public function send(): void
    {
        if (method_exists($this, 'dispatch')) {
            self::dispatch();
        } else {
            throw new \Exception("Queueing functionality is unavailable.");
        }
    }

    public function sendNow(): void
    {
        $channels = $this->via();
        foreach ($channels as $channel) {
            $channelClass = "\\Services\\Core\\Notifications\\Channels\\Hm_" . ucfirst($channel) . "Channel";
            if (class_exists($channelClass)) {
                $channelInstance = new $channelClass();
                if (method_exists($channelInstance, 'send')) {
                    $channelInstance->send($this);
                } else {
                    throw new \Exception("The channel {$channel} does not have a send method.");
                }
            }else {
                throw new \Exception("Channel {$channel} not found.");
            }
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }
}

