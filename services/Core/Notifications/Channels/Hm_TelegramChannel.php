<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;

/**
 * Class Hm_TelegramChannel
 * @package Services\Core\Notifications\Channels
 */
class Hm_TelegramChannel extends Hm_NotificationChannel
{
    /**
     * The Chatter instance.
     *
     * @var Chatter
     */
    private $chatter;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $config = Hm_Container::getContainer()->get('config');
        $telegramConfig = $config->get('telegram');
        $telegramToken = $telegramConfig['bot_token'];
        $telegramChatId = $telegramConfig['chat_id']; // ID du chat ou username (@username)
        
        $dsnString = sprintf('telegram://%s@default?channel=%s', $telegramToken, $telegramChatId);
        $dsn = new Dsn($dsnString);
        $factory = new TelegramTransportFactory();
        $transport = $factory->create($dsn);
        $this->chatter = new Chatter($transport);
    }

    /**
     * Send a Telegram message.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $chatMessage = new ChatMessage($notification->getContent());
        $this->chatter->send($chatMessage);
        echo "Message sent to Telegram!";
    }
}