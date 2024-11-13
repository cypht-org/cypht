<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Vonage\VonageOptions;
use Symfony\Component\Notifier\Bridge\Vonage\VonageTransport;

class Hm_VonageChannel extends Hm_NotificationChannel
{
    private $notifier;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $config = Hm_Container::getContainer()->get('config');
        $vonageConfig = $config->get('vonage');
        $vonageApiKey = $vonageConfig['api_key'];
        $vonageApiSecret = $vonageConfig['api_secret'];
        $vonageFromNumber = $vonageConfig['from'];
        $vonageTransport = new VonageTransport($vonageApiKey, $vonageApiSecret, $vonageFromNumber);
        $this->notifier = new Notifier([$vonageTransport]);
    }

    /**
     * Send an SMS message using Vonage.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $smsMessage = new SmsMessage($notification->getRecipient(), $notification->getMessageText());

        // Optionally, you can configure options for Vonage (like TTL, etc.)
        // $smsMessage->options(new VonageOptions());

        // Send the message via Vonage
        $this->notifier->send($smsMessage->getNotification());

        echo "Message sent via Vonage (Nexmo)!";
    }
}
