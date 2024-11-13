<?php

namespace Services\Core\Notifications;

use Symfony\Component\Notifier\Notifier;
use Services\Traits\Hm_Dispatchable;
use Services\Core\Queue\Hm_Queueable;
use Services\Contracts\Notifications\Hm_Dispatcher;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Notification class
 * package: Services\Core\Notifications
 */
class Hm_Notification extends Hm_Queueable implements Hm_Dispatcher
{
    use Hm_Dispatchable;
    /**
     * The notification driver.
     * 
     * @var string
     */
    public string $driver;
      /**
     * The notification title.
     * 
     * @var string
     */
    public string $title;

    /**
     * The notification lines.
     * 
     * @var array
     */
    public array $lines = []; 

    /**
     * The recipient of the notification.
     * 
     * @var Recipient
     */
    protected Recipient $recipient; 

    /**
     * Set the title of the notification.
     *
     * @param string $title
     * @return self
     */
    public function greeting(string $title): self
    {
        $this->title = $title;
        return $this;
    }

     /**
     * Add a line to the message of the notification.
     *
     * @param string $line
     * @return self
     */
    public function line(string $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    /**
     * Get the full message text, combining all lines.
     *
     * @return string
     */
    public function getMessageText(): string
    {
        return implode("\n", $this->lines);
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

    /**
     * Get the recipient of the notification.
     *
     * @return Recipient
     */
    public function getRecipient(): Recipient
    {
        return $this->recipient;
    }

    /**
     * Get the title of the notification.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set the recipient for the notification.
     *
     * @param mixed $recipient
     * @return self
     */
    static public function to(mixed $recipient): string
    {
        self::$recipient = new Recipient(
            is_array($recipient) ? implode(',', $recipient) : $recipient
        );
        return static::class;
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
                    $channelInstance->send($this->recipient, $this->title, $this->getMessageText());
                } else {
                    throw new \Exception("The channel {$channel} does not have a send method.");
                }
            }else {
                throw new \Exception("Channel {$channel} not found.");
            }
        }
    }
}

