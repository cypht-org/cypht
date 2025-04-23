<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Container;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;

/**
 * Class Hm_TwilioChannel
 * @package Services\Core\Notifications\Channels
 */
class TwilioChannel extends NotificationChannel
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
        $config = Container::getContainer()->get('config');
        $twilioConfig = $config->get('twilio');
        $twilioAccountSid = $twilioConfig['sid'];
        $twilioAuthToken = $twilioConfig['token'];
        $twilioFrom = $twilioConfig['from'];
        $dsnString = sprintf(
            'twilio://%s:%s@default?from=%s',
            $twilioAccountSid,
            $twilioAuthToken,
            $twilioFrom
        );
        $dsn = new Dsn($dsnString);
        $factory = new TwilioTransportFactory();
        $transport = $factory->create($dsn);
        $this->chatter = new Chatter($transport);
    }

    /**
     * Send an SMS message using Twilio.
     *
     * @param string $notifiable The recipient's phone number.
     * @param string $message The message content.
     * @param string $title The title or subject of the notification (optional).
     */
    /**
     * Send a Twilio SMS message.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $config = Container::getContainer()->get('config');
        $twilioTo = $config->get('twilio')['to'];

        $smsMessage = new SmsMessage($twilioTo, $notification->getContent());
        $this->chatter->send($smsMessage);
        echo "Message sent via Twilio!";
    }
}
