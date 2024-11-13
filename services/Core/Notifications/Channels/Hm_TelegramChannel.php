<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;

/**
 * Class Hm_TelegramChannel
 * @package Services\Core\Notifications\Channels
 */
class Hm_TelegramChannel extends Hm_NotificationChannel
{
    /**
     * The Notifier instance.
     *
     * @var Notifier
     */
    private $notifier;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $config = Hm_Container::getContainer()->get('config');
        $telegramConfig = $config->get('telegram');
        $telegramBotToken = $telegramConfig['bot_token'];
        $telegramChatId = $telegramConfig['chat_id'];
        $telegramTransport = new TelegramTransport($telegramBotToken, $telegramChatId);
        $this->notifier = new Notifier([$telegramTransport]);
    }

    /**
     * Send a Telegram message using the Telegram Bot.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $telegramMessage = new ChatMessage($notification->getMessageText());

        // Optionally add more Telegram message options (like parsing mode)
        $telegramMessage->options(
            (new TelegramOptions())
                ->parseMode(TelegramOptions::PARSE_MODE_MARKDOWN_V2)
        );

        $this->notifier->send($telegramMessage->getNotification());

        echo "Message sent via Telegram!";
    }
}
