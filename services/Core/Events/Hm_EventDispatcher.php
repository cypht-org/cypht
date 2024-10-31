<?php

namespace Services\Core\Events;

class Hm_EventDispatcher
{
    protected static array $listeners = [];

    public static function listen(string $eventClass, string $listenerClass): void
    {
        self::$listeners[$eventClass][] = $listenerClass;
    }

    public static function dispatch($event): void
    {
        $eventClass = get_class($event);   
        // Check if there are listeners for this event
        if (isset(self::$listeners[$eventClass])) {
            foreach (self::$listeners[$eventClass] as $listenerClass) {
                $listener = new $listenerClass();
                $listener->handle($event);
            }
        }
    }
}
