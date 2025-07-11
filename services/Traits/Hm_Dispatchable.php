<?php

namespace Services\Traits;

use Services\Core\Jobs\Hm_BaseJob;
use Services\Core\Events\Hm_BaseEvent;
use Services\Core\Jobs\Hm_JobDispatcher;
use Services\Core\Events\Hm_EventDispatcher;
use Services\Core\Notifications\Hm_Notification;
use Services\Core\Notifications\Hm_NotificationDispatcher;

trait Hm_Dispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        $instance = new static(...$arguments);
        if (is_subclass_of($instance, Hm_BaseJob::class)) {
            Hm_JobDispatcher::dispatch($instance);
        }elseif(is_subclass_of($instance, Hm_BaseEvent::class)){
            Hm_EventDispatcher::dispatch($instance);
        }elseif(is_subclass_of($instance, Hm_Notification::class)){
            Hm_NotificationDispatcher::send($instance);
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
