<?php

namespace Services\Providers;

use Services\Core\Events\Hm_EventDispatcher;
use Services\Listeners\Hm_NewMaiListener;
use Services\Events\Hm_NewEmailProcessedEvent;

class Hm_EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected array $listen = [
        Hm_NewEmailProcessedEvent::class => [
            Hm_NewMaiListener::class,
        ],
    ];

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Hm_EventDispatcher::listen($event, $listener);
            }
        }
    }
}
