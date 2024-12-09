<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;

/**
 * Class Hm_SlackChannel
 * @package Services\Core\Notifications\Channels
 */
class Hm_SlackChannel extends Hm_NotificationChannel
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
        $slackConfig = $config->get('slack');
        $slackToken = $slackConfig['token'];
        $slackChannel = $slackConfig['channel'];     
        $dsnString = sprintf('slack://%s@default?channel=%s', $slackToken, $slackChannel);
        $dsn = new Dsn($dsnString);
        $factory = new SlackTransportFactory();
        $transport = $factory->create($dsn);
        $this->chatter = new Chatter($transport);
    }

    /**
     * Send a Slack message.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $chatMessage = new ChatMessage($notification->getContent());
        $this->chatter->send($chatMessage);
        echo "Message sent to Slack!";
    }
}
