<?php

namespace Services\Core\Notifications;

use Symfony\Component\Notifier\Notifier;
use Services\Core\Notifications\Channels\Hm_SlackChannel;
use Services\Core\Notifications\Channels\Hm_TwilioChannel;
use Services\Core\Notifications\Channels\Hm_TelegramChannel;

class Hm_Notification
{
    public function __construct(private array $config = [])
    {
        $this->config = $config; // Set configuration in the constructor
    }

    public function via(): array
    {
        return $this->config['channels'] ?? ['slack', 'telegram'];
    }

    public function send($notifiable, string $title, string $content): void
    {
        $channels = $this->via();
        foreach ($channels as $channel) {
            $this->sendThroughChannel($channel, $notifiable, $content);
        }
    }

    private function sendThroughChannel(string $channel, $notifiable, string $message): void
    {
        switch ($channel) {
            case 'slack':
                (new Hm_SlackChannel(new SlackTransport()))->send($notifiable, $message);
                break;
            case 'telegram':
                (new Hm_TelegramChannel(new TelegramTransport()))->send($notifiable, $message);
                break;
            case 'twilio':
                (new Hm_TwilioChannel(new TwilioTransport()))->send($notifiable, $message);
                break;
            // Add more channels as necessary
        }
    }
}

