<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackContextBlock;

/**
 * Class Hm_SlackChannel
 * @package Services\Core\Notifications\Channels
 */
class Hm_SlackChannel extends Hm_NotificationChannel
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
        $slackConfig = $config->get('slack');
        $slackToken = $slackConfig['token'];
        $slackChannel = $slackConfig['channel'];        
        $slackTransport = new SlackTransport($slackToken, $slackChannel);        
        $this->notifier = new Notifier([$slackTransport]);
    }

    /**
     * Send a Slack message.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $slackMessage = new ChatMessage($notification->getTitle());
        $contextBlock = (new SlackContextBlock())
            ->text($notification->getMessageText());
        // Optionally, configure options (e.g., set icon, etc.)
        $slackMessage->options((new SlackOptions())->block($contextBlock));

        // Send the message to Slack
        $this->notifier->send($slackMessage->getNotification());

        echo "Message sent to Slack!";
    }
}
