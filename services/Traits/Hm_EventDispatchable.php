<?php

namespace Services\Traits;

use Services\Events\Hm_EventManager;

trait Hm_EventDispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        $event = new static(...$arguments);
        // Call the event listener or handle it in some way
        Hm_EventManager::dispatch($event);
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
