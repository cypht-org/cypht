<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransport;

/**
 * Class Hm_TwilioChannel
 * @package Services\Core\Notifications\Channels
 */
class Hm_TwilioChannel extends Hm_NotificationChannel
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
        $twilioConfig = $config->get('twilio');
        $twilioTransport = new TwilioTransport($twilioConfig['sid'], $twilioConfig['token'], $twilioConfig['from']);
        $this->notifier = new Notifier([$twilioTransport]);
    }

    /**
     * Send an SMS message using Twilio.
     *
     * @param string $notifiable The recipient's phone number.
     * @param string $message The message content.
     * @param string $title The title or subject of the notification (optional).
     */
    public function send($notification): void
    {
        $smsMessage = new SmsMessage($notification->getRecipient(), $notification->getMessageText());
        // Send the message via Twilio
        $this->notifier->send($smsMessage->getNotification());

        echo "Message sent via Twilio!";
    }
}
