<?php

namespace Services\Core\Notifications;

use Services\Core\Container;
use Services\Contracts\Queue\ShouldQueue;
use Services\Contracts\Notifications\Factory;
use Services\Core\Notifications\Channels\{BroadcastChannel, SlackChannel, TwilioChannel, TelegramChannel, VonageChannel };

/**
 * Notification dispatcher class
 * package: Services\Core\Notifications
 */
class NotificationDispatcher implements Factory
{
    /**
     * Mapping of channel names to their respective classes
     * 
     * @var array
     */
    protected static array $channelClasses = [
        'slack'    => SlackChannel::class,
        'vonage'   => VonageChannel::class,
        'twilio'   => TwilioChannel::class,
        'telegram' => TelegramChannel::class,
        'broadcast' => BroadcastChannel::class,
    ];
    /**
     * Dispatch a notification to all registered channels.
     *
     * @param Hm_Notification $notification The notification instance
     */
    public static function send(Notification $notification): void
    {
        $driver = $notification->driver;
        $queueDriver = Container::getContainer()->get('queue.manager')->getDriver($driver);
        if ($queueDriver) {
            $queueDriver->push($notification);
        } else {
            throw new \Exception("Queue driver {$driver} not found.");
        }
    }

    /**
     * Dispatch a notification immediately, bypassing the queue.
     *
     * @param BaseNotification $notification The notification instance
     */
    public static function sendNow(Notification $notification): void
    {
        $channels = $notification->via();
        foreach ($channels as $channelName) {
            if (isset(self::$channelClasses[$channelName])) {
                $channelClass = self::channel($channelName);
                $channel = new $channelClass();
                $channel->send($notification);
            } else {
                throw new \Exception("Channel {$channelName} is not registered or implemented.");
            }
        }
    }

    static public function channel($name = null) {
        if ($name) {
            return self::$channelClasses[$name];
        }
        return self::$channelClasses;
    }
}
