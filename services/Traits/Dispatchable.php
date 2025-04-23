<?php

namespace Services\Traits;

use Services\Core\Jobs\BaseJob;
use Services\Core\Events\BaseEvent;
use Services\Core\Jobs\JobDispatcher;
use Services\Core\Events\EventDispatcher;
use Services\Core\Notifications\Notification;
use Services\Core\Notifications\NotificationDispatcher;

trait Dispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        $instance = new static(...$arguments);
        if (is_subclass_of($instance, BaseJob::class)) {
            JobDispatcher::dispatch($instance);
        }elseif(is_subclass_of($instance, BaseEvent::class)){
            EventDispatcher::dispatch($instance);
        }elseif(is_subclass_of($instance, Notification::class)){
            NotificationDispatcher::send($instance);
        }else{
            throw new \Exception("Class must be an instance of Hm_BaseJob or Hm_BaseEvent");
        }
    }

    /**
     * Dispatch the event with the given arguments if the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return void
     */
    public static function dispatchIf($boolean, ...$arguments)
    {
        if ($boolean) {
            self::dispatch(...$arguments);
        }
    }

    /**
     * Dispatch the event with the given arguments unless the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return void
     */
    public static function dispatchUnless($boolean, ...$arguments)
    {
        if (!$boolean) {
            self::dispatch(...$arguments);
        }
    }
}
