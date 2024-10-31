<?php

namespace Services\Traits;

use Services\Core\Events\Hm_EventDispatcher;
use Services\Core\Jobs\Hm_BaseJob;
use Services\Core\Queue\Hm_JobDispatcher;

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
            # code...
            Hm_JobDispatcher::dispatch($instance);
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
