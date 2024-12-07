<?php

namespace Services\Core\Notifications;

use Services\Core\Hm_Container;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Contracts\Notifications\Hm_Factory;
use Services\Core\Notifications\Channels\{Hm_BroadcastChannel, Hm_SlackChannel, Hm_TwilioChannel, Hm_TelegramChannel, Hm_VonageChannel };

/**
 * Notification dispatcher class
 * package: Services\Core\Notifications
 */
class Hm_NotificationDispatcher implements Hm_Factory
{
    /**
     * Mapping of channel names to their respective classes
     * 
     * @var array
     */
    protected static array $channelClasses = [
        'slack'    => Hm_SlackChannel::class,
        'vonage'   => Hm_VonageChannel::class,
        'twilio'   => Hm_TwilioChannel::class,
        'telegram' => Hm_TelegramChannel::class,
        'broadcast' => Hm_BroadcastChannel::class,
    ];
    /**
     * Dispatch a notification to all registered channels.
     *
     * @param Hm_Notification $notification The notification instance
     */
    public static function send(Hm_Notification $notification): void
    {
        $channels = $notification->via();
        foreach ($channels as $channelName) {
            if (isset(self::$channelClasses[$channelName])) {
                $channelClass = self::channel($channelName);
                $channel = new $channelClass();
                if (is_subclass_of($notification, Hm_ShouldQueue::class)) {
                    $driver = $notification->driver;
                    $queueDriver = Hm_Container::getContainer()->get('queue.manager')->getDriver($driver);
                    if ($queueDriver) {
                        $queueDriver->push($notification);
                    } else {
                        throw new \Exception("Queue driver {$driver} not found.");
                    }
                } else {
                    // Send notification immediately if not queued
                    $channel->send($notification);
                }
            } else {
                throw new \Exception("Channel {$channelName} is not registered or implemented.");
            }
        }
    }

    /**
     * Dispatch a notification immediately, bypassing the queue.
     *
     * @param BaseNotification $notification The notification instance
     */
    public static function sendNow(Hm_Notification $notification): void
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
