<?php

namespace Services\Core\Notifications\Channels;

use Services\Core\Hm_Container;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;

class Hm_VonageChannel extends Hm_NotificationChannel
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
        $vonageConfig = $config->get('vonage');

        $vonageApiKey = $vonageConfig['api_key'];
        $vonageApiSecret = $vonageConfig['api_secret'];
        $vonageFrom = $vonageConfig['from'];
        
        $dsnString = sprintf(
            'vonage://%s:%s@default?from=%s',
            $vonageApiKey,
            $vonageApiSecret,
            $vonageFrom
        );
        $dsn = new Dsn($dsnString);
        $factory = new VonageTransportFactory();
        $transport = $factory->create($dsn);
        $this->chatter = new Chatter($transport);
    }

    /**
     * Send a Vonage SMS message.
     *
     * @param Hm_Notification $notification The notification object.
     */
    public function send($notification): void
    {
        $config = Hm_Container::getContainer()->get('config');
        $vonageTo = $config->get('vonage')['to'];

        $smsMessage = new SmsMessage($vonageTo, $notification->getContent());
        $this->chatter->send($smsMessage);
        echo "Message sent via Vonage!";
    }
}
